#!/usr/bin/env python3
"""
SessionEnd hook: extract feedback signals from the conversation transcript.

- Reads SessionEnd hook JSON from stdin
- Loads the JSONL transcript at transcript_path
- Calls `claude -p` (Haiku) to extract feedback candidates
- Matches against existing feedback_*.md files in memory dir
  - If matched: increments pain_count in frontmatter
  - If new:    appends to memory/_inbox/YYYY-MM-DD.md for later review
- Writes a debug log to memory/_inbox/_log.jsonl
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import sys
from datetime import datetime
from pathlib import Path

MEMORY_DIR = Path("/Users/hirotaseiji/.claude/projects/-Users-hirotaseiji-Desktop---OS/memory")
INBOX_DIR = MEMORY_DIR / "_inbox"
LOG_FILE = INBOX_DIR / "_log.jsonl"
MAX_TURNS = 80
MAX_TURN_CHARS = 1500
MODEL = "claude-haiku-4-5-20251001"


def log(payload: dict) -> None:
    INBOX_DIR.mkdir(parents=True, exist_ok=True)
    payload["ts"] = datetime.now().isoformat(timespec="seconds")
    with LOG_FILE.open("a") as f:
        f.write(json.dumps(payload, ensure_ascii=False) + "\n")


def read_transcript(path: Path) -> list[str]:
    """Return a list of `ROLE: text` lines from a Claude Code transcript JSONL."""
    turns: list[str] = []
    with path.open() as f:
        for raw in f:
            raw = raw.strip()
            if not raw:
                continue
            try:
                row = json.loads(raw)
            except json.JSONDecodeError:
                continue

            role = row.get("type") or row.get("role")
            msg = row.get("message")
            if isinstance(msg, dict):
                content = msg.get("content")
            else:
                content = row.get("content")

            text = ""
            if isinstance(content, str):
                text = content
            elif isinstance(content, list):
                parts = []
                for c in content:
                    if isinstance(c, dict) and c.get("type") == "text":
                        parts.append(c.get("text", ""))
                text = "\n".join(p for p in parts if p)

            text = text.strip()
            if not text:
                continue

            tag = "USER" if role in ("user", "human") else (
                "ASSISTANT" if role == "assistant" else None
            )
            if tag is None:
                continue
            turns.append(f"{tag}: {text[:MAX_TURN_CHARS]}")
    return turns


def existing_feedback_index() -> str:
    """One-line summary per existing feedback_*.md so the model can dedupe."""
    lines = []
    for p in sorted(MEMORY_DIR.glob("feedback_*.md")):
        text = p.read_text()
        desc_match = re.search(r"^description:\s*(.+)$", text, re.MULTILINE)
        desc = desc_match.group(1).strip() if desc_match else p.stem
        lines.append(f"- {p.stem}: {desc}")
    return "\n".join(lines) if lines else "(まだフィードバックメモリはありません)"


def call_claude(prompt: str) -> tuple[str, str]:
    try:
        proc = subprocess.run(
            ["claude", "-p", prompt, "--model", MODEL],
            capture_output=True,
            text=True,
            timeout=180,
        )
        return proc.stdout.strip(), proc.stderr.strip()
    except subprocess.TimeoutExpired:
        return "", "timeout"
    except FileNotFoundError:
        return "", "claude CLI not found on PATH"


def bump_pain_count(target_stem: str) -> int | None:
    """Increment pain_count in frontmatter of memory/<stem>.md. Returns new count or None."""
    path = MEMORY_DIR / f"{target_stem}.md"
    if not path.exists():
        return None
    text = path.read_text()
    if not text.startswith("---"):
        return None
    end = text.find("---", 3)
    if end == -1:
        return None
    fm = text[3:end]
    body = text[end + 3 :]

    m = re.search(r"^pain_count:\s*(\d+)\s*$", fm, re.MULTILINE)
    if m:
        new_count = int(m.group(1)) + 1
        fm = re.sub(
            r"^pain_count:\s*\d+\s*$",
            f"pain_count: {new_count}",
            fm,
            count=1,
            flags=re.MULTILINE,
        )
    else:
        new_count = 1
        fm = fm.rstrip("\n") + f"\npain_count: {new_count}\n"

    path.write_text(f"---{fm}---{body}")
    return new_count


def main() -> int:
    INBOX_DIR.mkdir(parents=True, exist_ok=True)

    try:
        event = json.load(sys.stdin)
    except Exception as e:
        log({"stage": "stdin", "error": str(e)})
        return 0

    transcript_path = event.get("transcript_path")
    session_id = (event.get("session_id") or "unknown")[:8]
    if not transcript_path or not Path(transcript_path).exists():
        log({"stage": "transcript_missing", "path": transcript_path, "session": session_id})
        return 0

    turns = read_transcript(Path(transcript_path))
    if not turns:
        log({"stage": "empty_transcript", "session": session_id})
        return 0

    convo = "\n\n".join(turns[-MAX_TURNS:])
    index = existing_feedback_index()

    prompt = f"""あなたは誠司さんの「自分OS」のメタ観察役です。
以下の会話ログから、誠司さんが Claude（あなたの前回セッション）に対して
「こうしてほしい」「こうしないでほしい」と指摘・要望した内容（フィードバック）だけを抽出してください。

# 既存のフィードバックメモリ一覧（重複判定用）
{index}

# 会話ログ（直近 {MAX_TURNS} ターン）
{convo}

# 出力ルール
- 1行1件
- 既存メモリと意味が重複するなら: `MATCH: <メモリのファイル名（拡張子なし）> | <該当発言の要約>`
- 新しいフィードバックなら: `NEW: <ルール本体（1行）> | Why: <理由> | <該当発言の要約>`
- フィードバックが1件もなければ `NONE` だけを出力
- 雑談・タスク依頼・質問・事実確認は無視
- 推測ではなく、誠司さん本人の発言に明確な根拠があるものだけ

出力のみ。前置き・後書き不要。"""

    stdout, stderr = call_claude(prompt)
    log(
        {
            "stage": "claude_done",
            "session": session_id,
            "turns": len(turns),
            "stdout_len": len(stdout),
            "stderr": stderr[:200] if stderr else "",
        }
    )

    if not stdout or stdout.strip() == "NONE":
        return 0

    today = datetime.now().strftime("%Y-%m-%d")
    inbox_file = INBOX_DIR / f"{today}.md"

    new_lines: list[str] = []
    bumped: list[tuple[str, int]] = []

    for line in stdout.splitlines():
        line = line.strip()
        if not line:
            continue
        match_m = re.match(r"^MATCH:\s*([^\s|]+)\s*\|", line)
        if match_m:
            stem = match_m.group(1).removesuffix(".md")
            count = bump_pain_count(stem)
            if count is not None:
                bumped.append((stem, count))
            else:
                new_lines.append(f"- (MATCH指定だが該当ファイルなし) {line}")
            continue
        if line.startswith("NEW:") or line.startswith("- NEW:"):
            new_lines.append(line.lstrip("- ").strip())

    if new_lines or bumped:
        with inbox_file.open("a") as f:
            f.write(f"\n## {datetime.now().strftime('%H:%M')} session:{session_id}\n\n")
            for stem, count in bumped:
                threshold_note = "  ← **3回到達: CLAUDE.md昇格を検討**" if count >= 3 else ""
                f.write(f"- BUMP: `{stem}` → pain_count={count}{threshold_note}\n")
            for nl in new_lines:
                f.write(f"- {nl}\n")

    log(
        {
            "stage": "wrote_inbox",
            "session": session_id,
            "new": len(new_lines),
            "bumped": [{"stem": s, "count": c} for s, c in bumped],
        }
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
