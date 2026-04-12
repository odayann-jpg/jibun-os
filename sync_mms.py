#!/usr/bin/env python3
"""MMS ↔ 自分OS 双方向同期スクリプト

使い方:
    python3 sync_mms.py pull            # MMS(Firebase) → tasks/mms-tasks.md
    python3 sync_mms.py push            # tasks/mms-tasks.md の変更 → MMS(Firebase)
    python3 sync_mms.py sync-coordinate # コーディネートカテゴリ + Googleカレンダー同期
    python3 sync_mms.py                 # デフォルト: pull
"""

import json
import os
import sys
import uuid
import requests
from pathlib import Path
from datetime import datetime, timedelta, timezone

try:
    from zoneinfo import ZoneInfo
except ImportError:  # Python < 3.9 では未使用想定
    ZoneInfo = None

# Firebase設定
FB_PROJECT = 'task-78c9f'
FB_KEY = 'AIzaSyDALc9hialR10cqtv7vNOOisk6yLO4-sXc'
FB_URL = f'https://firestore.googleapis.com/v1/projects/{FB_PROJECT}/databases/(default)/documents/mms/main'

BASE_DIR = Path(__file__).parent
TASKS_MD = BASE_DIR / 'tasks' / 'mms-tasks.md'
TASKS_JSON = BASE_DIR / 'tasks' / 'mms-data.json'

STATUS_LABELS = {
    '未': '⬜ 未着手',
    '進行': '🔵 進行中',
    '確認待ち': '🟡 確認待ち',
    '完了': '✅ 完了',
}


# ─────────────────────────────────────────────
# Firebase I/O
# ─────────────────────────────────────────────

def fetch_firebase():
    """Firebaseから全データを取得"""
    resp = requests.get(f'{FB_URL}?key={FB_KEY}', timeout=10)
    resp.raise_for_status()
    raw = resp.json()
    return json.loads(raw['fields']['payload']['stringValue'])


def save_firebase(data):
    """全データをFirebaseに書き戻す"""
    body = {
        'fields': {
            'payload': {'stringValue': json.dumps(data, ensure_ascii=False)}
        }
    }
    resp = requests.patch(f'{FB_URL}?key={FB_KEY}', json=body, timeout=10)
    resp.raise_for_status()


# ─────────────────────────────────────────────
# PULL: Firebase → mms-tasks.md
# ─────────────────────────────────────────────

def pull():
    """MMS のタスクを取得して tasks/mms-tasks.md に書き出す"""
    print('MMS からデータを取得中...')
    data = fetch_firebase()

    # 生データをローカルに保存（push 時の差分比較用）
    TASKS_JSON.write_text(
        json.dumps(data, ensure_ascii=False, indent=2), encoding='utf-8'
    )

    spaces = {s['id']: s.get('name', '?') for s in data.get('spaces', [])}
    tasks = [t for t in data.get('tasks', []) if t.get('status') != '完了']
    logs = data.get('logs', [])

    now = datetime.now().strftime('%Y-%m-%d %H:%M')

    lines = [
        '# MMS タスク一覧',
        '',
        f'> 最終同期: {now}',
        '> ステータスを変更したら `python3 sync_mms.py push` で MMS に反映できます。',
        '',
        '## 凡例',
        '| ステータス値 | 意味 |',
        '|---|---|',
        '| 未 | 未着手 |',
        '| 進行 | 進行中 |',
        '| 確認待ち | 確認待ち |',
        '| 完了 | 完了（pushで反映） |',
        '',
    ]

    # スペース（事業）別に整理
    from collections import defaultdict
    by_space = defaultdict(list)
    for task in tasks:
        sid = task.get('spaceId', '')
        by_space[sid].append(task)

    # スペースの順序を維持
    space_order = [s['id'] for s in data.get('spaces', [])]
    # スペースに属さないタスクも含める
    all_sids = space_order + [sid for sid in by_space if sid not in space_order]

    for sid in all_sids:
        if sid not in by_space:
            continue
        space_name = spaces.get(sid, '未分類')
        stasks = sorted(by_space[sid], key=lambda t: (t.get('deadline') or '9999'))

        lines.append(f'## {space_name}')
        lines.append('')
        lines.append('| ID | タスク | 期限 | ステータス | カテゴリ |')
        lines.append('|---|---|---|---|---|')

        for task in stasks:
            tid = task.get('id', '')
            title = task.get('title', '').replace('|', '｜')
            deadline = task.get('deadline', '') or ''
            status = task.get('status', '未')
            category = task.get('category', '') or ''
            tags = ' '.join(f'#{t}' for t in task.get('tags', []))
            tag_str = f' {tags}' if tags else ''
            lines.append(f'| {tid} | {title}{tag_str} | {deadline} | {status} | {category} |')

        lines.append('')

    # サブタスクがあるものを別途表示
    subtask_section = []
    for task in tasks:
        subs = task.get('subtasks', [])
        if subs:
            subtask_section.append(f'### {task.get("title", "")}')
            for sub in subs:
                done = '✅' if sub.get('done') else '⬜'
                subtask_section.append(f'- {done} {sub.get("title", "")}')
            subtask_section.append('')

    if subtask_section:
        lines.append('## サブタスク詳細')
        lines.append('')
        lines.extend(subtask_section)

    # 完了ログ（直近10件）
    lines.append('## 完了ログ（直近10件）')
    lines.append('')
    recent_logs = sorted(logs, key=lambda l: l.get('completedAt', ''), reverse=True)[:10]
    for log in recent_logs:
        title = log.get('taskTitle') or log.get('title', '')
        completed_at = (log.get('completedAt') or '')[:10]
        lines.append(f'- [{completed_at}] {title}')

    TASKS_MD.write_text('\n'.join(lines), encoding='utf-8')

    active = len([t for t in tasks if t.get('status') in ('未', '進行', '確認待ち')])
    print(f'完了: {active} 件のアクティブタスク → tasks/mms-tasks.md に保存')


# ─────────────────────────────────────────────
# PUSH: mms-tasks.md の変更 → Firebase
# ─────────────────────────────────────────────

def push():
    """mms-tasks.md のステータス変更を MMS(Firebase) に反映"""
    if not TASKS_MD.exists():
        print('エラー: tasks/mms-tasks.md が見つかりません。先に pull を実行してください。')
        sys.exit(1)
    if not TASKS_JSON.exists():
        print('エラー: tasks/mms-data.json が見つかりません。先に pull を実行してください。')
        sys.exit(1)

    print('MMS の最新データを取得中...')
    data = fetch_firebase()
    tasks_by_id = {t['id']: t for t in data.get('tasks', [])}

    content = TASKS_MD.read_text(encoding='utf-8')
    changed = []

    for line in content.splitlines():
        if not line.startswith('| ') or '|---|' in line or line.startswith('| ID ') or line.startswith('| ステータス'):
            continue
        parts = [p.strip() for p in line.split('|')]
        # | ID | タスク | 期限 | ステータス | カテゴリ |
        # parts[0]='', parts[1]=ID, parts[2]=title, parts[3]=deadline, parts[4]=status, parts[5]=category
        if len(parts) < 6:
            continue
        tid = parts[1]
        new_status = parts[4]

        if tid not in tasks_by_id:
            continue
        if new_status not in ('未', '進行', '確認待ち', '完了'):
            continue

        old_status = tasks_by_id[tid].get('status', '未')
        if old_status != new_status:
            tasks_by_id[tid]['status'] = new_status
            if new_status == '完了':
                tasks_by_id[tid]['completedAt'] = datetime.now().isoformat()
            changed.append((tasks_by_id[tid].get('title', tid), old_status, new_status))

    if not changed:
        print('変更なし。ステータスを編集してから push してください。')
        return

    print(f'{len(changed)} 件の変更を検出:')
    for title, old, new in changed:
        print(f'  {title}: {old} → {new}')

    data['tasks'] = list(tasks_by_id.values())

    # 完了タスクをログに移動
    if any(new == '完了' for _, _, new in changed):
        completed_ids = {t['id'] for t in data['tasks'] if t.get('status') == '完了'}
        if 'logs' not in data:
            data['logs'] = []
        for t in data['tasks']:
            if t['id'] in completed_ids:
                data['logs'].append(t)
        data['tasks'] = [t for t in data['tasks'] if t['id'] not in completed_ids]

    print('Firebase に書き込み中...')
    save_firebase(data)
    print('完了!')


# ─────────────────────────────────────────────
# ADD: タスクを新規追加
# ─────────────────────────────────────────────

def add_task(args):
    """新しいタスクを MMS に追加する
    使い方: python3 sync_mms.py add "タスク名" [--space スペース名] [--deadline YYYY-MM-DD] [--category カテゴリ]
    """
    import argparse
    import uuid

    parser = argparse.ArgumentParser()
    parser.add_argument('title', help='タスクのタイトル')
    parser.add_argument('--space', default='', help='事業スペース名')
    parser.add_argument('--deadline', default='', help='期限 (YYYY-MM-DD)')
    parser.add_argument('--category', default='その他', help='カテゴリ')
    opts = parser.parse_args(args)

    print('MMS の最新データを取得中...')
    data = fetch_firebase()
    spaces = {s.get('name', ''): s.get('id', '') for s in data.get('spaces', [])}

    space_id = spaces.get(opts.space, '')
    if opts.space and not space_id:
        print(f'警告: スペース "{opts.space}" が見つかりません。未分類として追加します。')
        print(f'利用可能なスペース: {", ".join(spaces.keys())}')

    new_task = {
        'id': str(uuid.uuid4())[:8],
        'title': opts.title,
        'spaceId': space_id,
        'deadline': opts.deadline,
        'status': '未',
        'category': opts.category,
        'tags': [],
        'subtasks': [],
        'createdAt': datetime.now().isoformat(),
    }

    data.setdefault('tasks', []).append(new_task)
    save_firebase(data)
    print(f'追加完了: [{new_task["id"]}] {opts.title} (期限: {opts.deadline or "なし"})')
    pull()  # ローカルも更新


# ─────────────────────────────────────────────
# DONE: タスクを完了にする
# ─────────────────────────────────────────────

def done_task(task_id_or_title):
    """タスクを完了にして Firebase に反映する
    使い方: python3 sync_mms.py done <タスクID または タイトルの一部>
    """
    print('MMS の最新データを取得中...')
    data = fetch_firebase()
    tasks = data.get('tasks', [])

    matched = [
        t for t in tasks
        if t.get('id') == task_id_or_title or task_id_or_title in t.get('title', '')
    ]

    if not matched:
        print(f'タスクが見つかりません: {task_id_or_title}')
        sys.exit(1)
    if len(matched) > 1:
        print(f'複数のタスクが見つかりました:')
        for t in matched:
            print(f'  [{t["id"]}] {t["title"]}')
        print('IDを指定して再実行してください。')
        sys.exit(1)

    task = matched[0]
    task['status'] = '完了'
    task['completedAt'] = datetime.now().isoformat()

    # logsに移動
    data.setdefault('logs', []).append(task)
    data['tasks'] = [t for t in tasks if t['id'] != task['id']]

    save_firebase(data)
    print(f'完了: [{task["id"]}] {task["title"]}')
    pull()  # ローカルも更新


# ─────────────────────────────────────────────
# LIST: 現在のタスク一覧を表示
# ─────────────────────────────────────────────

def list_tasks():
    """tasks/mms-tasks.md がなければ pull してから表示"""
    if not TASKS_MD.exists():
        pull()
    print(TASKS_MD.read_text(encoding='utf-8'))


# ─────────────────────────────────────────────
# GCAL-IMPORT: Googleカレンダー → MMS（未連携の予定のみ追加）
# ─────────────────────────────────────────────

JST = ZoneInfo('Asia/Tokyo') if ZoneInfo else timezone(timedelta(hours=9))


def _event_deadline_and_detail(event):
    """カレンダー1件から期限(YYYY-MM-DD)と detail 用の短い文面を返す。"""
    start = event.get('start') or {}
    if start.get('dateTime'):
        raw = start['dateTime'].replace('Z', '+00:00')
        dt = datetime.fromisoformat(raw)
        if ZoneInfo:
            dt = dt.astimezone(JST)
        else:
            dt = dt.astimezone(timezone.utc).astimezone(JST)
        return dt.strftime('%Y-%m-%d'), dt.strftime('%m月%d日 %H:%M〜')
    if start.get('date'):
        d = start['date'][:10]
        return d, f'{d} 終日'
    return '', ''


def _infer_category(title, default_category):
    t = title or ''
    if 'コーディネート' in t:
        return 'コーディネート'
    return default_category


def _new_task_id(existing_ids):
    for _ in range(32):
        tid = str(uuid.uuid4()).replace('-', '')[:12]
        if tid not in existing_ids:
            return tid
    return str(uuid.uuid4()).replace('-', '')


def ensure_category_in_data(data, space_name, category_name):
    """spaces の categories に category_name を追加。変更があれば True。"""
    for sp in data.get('spaces', []):
        if sp.get('name') != space_name:
            continue
        cats = sp.setdefault('categories', [])
        if category_name in cats:
            return False
        if '事前準備' in cats:
            cats.insert(cats.index('事前準備') + 1, category_name)
        elif 'サービス提供' in cats:
            cats.insert(cats.index('サービス提供'), category_name)
        else:
            cats.append(category_name)
        return True
    raise ValueError(f'スペース「{space_name}」が見つかりません')


def _fetch_calendar_events(service, calendar_id, days):
    """Google Calendar API で直近 days 日の予定を列挙。"""
    now = datetime.now(timezone.utc)
    time_min = now.isoformat()
    time_max = (now + timedelta(days=days)).isoformat()
    items = []
    page_token = None
    while True:
        res = (
            service.events()
            .list(
                calendarId=calendar_id,
                timeMin=time_min,
                timeMax=time_max,
                singleEvents=True,
                orderBy='startTime',
                maxResults=250,
                pageToken=page_token,
            )
            .execute()
        )
        items.extend(res.get('items', []))
        page_token = res.get('nextPageToken')
        if not page_token:
            break
    return items


def _get_calendar_service_and_id():
    """OAuth（tasks/.gcal-oauth/credentials.json 優先）またはサービスアカウントで Calendar API を返す。"""
    try:
        from googleapiclient.discovery import build
    except ImportError:
        print(
            'エラー: google-api-python-client が必要です。\n'
            '  pip install google-auth google-api-python-client google-auth-oauthlib'
        )
        sys.exit(1)

    SCOPES = ['https://www.googleapis.com/auth/calendar.readonly']
    base = BASE_DIR / 'tasks' / '.gcal-oauth'
    client_secrets = Path(
        os.environ.get('MMS_GCAL_OAUTH_CLIENT', str(base / 'credentials.json'))
    ).expanduser()
    token_path = Path(
        os.environ.get('MMS_GCAL_OAUTH_TOKEN', str(base / 'token.json'))
    ).expanduser()

    if client_secrets.is_file():
        try:
            from google.auth.transport.requests import Request
            from google.oauth2.credentials import Credentials
            from google_auth_oauthlib.flow import InstalledAppFlow
        except ImportError:
            print(
                'エラー: OAuth 用に google-auth-oauthlib が必要です。\n'
                '  pip install google-auth-oauthlib'
            )
            sys.exit(1)
        creds = None
        if token_path.is_file():
            creds = Credentials.from_authorized_user_file(str(token_path), SCOPES)
        if not creds or not creds.valid:
            if creds and creds.expired and creds.refresh_token:
                creds.refresh(Request())
            else:
                flow = InstalledAppFlow.from_client_secrets_file(
                    str(client_secrets), SCOPES
                )
                creds = flow.run_local_server(port=0)
            token_path.parent.mkdir(parents=True, exist_ok=True)
            token_path.write_text(creds.to_json(), encoding='utf-8')
        cal_id = os.environ.get('MMS_GCAL_CALENDAR_ID', 'primary').strip() or 'primary'
        return build('calendar', 'v3', credentials=creds, cache_discovery=False), cal_id

    cred_path = (
        os.environ.get('MMS_GCAL_CREDENTIALS', '').strip()
        or os.environ.get('GOOGLE_APPLICATION_CREDENTIALS', '').strip()
    )
    cal_id = os.environ.get('MMS_GCAL_CALENDAR_ID', '').strip()
    if cred_path and Path(cred_path).is_file() and cal_id:
        from google.oauth2 import service_account

        creds = service_account.Credentials.from_service_account_file(
            cred_path, scopes=SCOPES
        )
        return build('calendar', 'v3', credentials=creds, cache_discovery=False), cal_id

    print(
        'Googleカレンダーに接続できません。次のいずれかを設定してください。\n\n'
        '【推奨】OAuth（メインの「primary」カレンダー可）\n'
        '  1. Google Cloud Console で OAuth 2.0 クライアント ID（デスクトップアプリ）を作成し JSON を取得\n'
        '  2. 次のいずれかに保存:\n'
        f'       {BASE_DIR / "tasks" / ".gcal-oauth" / "credentials.json"}\n'
        '     または export MMS_GCAL_OAUTH_CLIENT=/path/to/client_secret.json\n'
        '  3. 初回実行でブラウザが開くので Google アカウントで許可（token.json が自動保存）\n'
        '  4. 任意: export MMS_GCAL_CALENDAR_ID=primary（省略時は primary）\n\n'
        '【代替】サービスアカウント（カレンダーを SA に共有した場合）\n'
        '  export MMS_GCAL_CREDENTIALS=/path/to/sa.json\n'
        '  export MMS_GCAL_CALENDAR_ID=xxxx@group.calendar.google.com\n'
    )
    sys.exit(1)


def _plan_gcal_import_tasks(
    data, space_id, items, *, default_category, coordinate_only=False
):
    """カレンダー予定から MMS に追加するタスク辞書のリストを組み立てる（未保存）。"""
    existing_gcal = set()
    for t in data.get('tasks', []):
        if t.get('gcalId'):
            existing_gcal.add(t['gcalId'])
    for log in data.get('logs', []):
        if log.get('gcalId'):
            existing_gcal.add(log['gcalId'])
    existing_ids = set()
    for t in data.get('tasks', []):
        if t.get('id'):
            existing_ids.add(t['id'])
    for log in data.get('logs', []):
        if log.get('id'):
            existing_ids.add(log['id'])

    new_tasks = []
    for ev in items:
        if ev.get('status') == 'cancelled':
            continue
        gid = ev.get('id')
        if not gid or gid in existing_gcal:
            continue
        title = (ev.get('summary') or '(無題)').strip()
        if coordinate_only and 'コーディネート' not in title:
            continue
        deadline, detail = _event_deadline_and_detail(ev)
        category = 'コーディネート' if coordinate_only else _infer_category(
            title, default_category
        )
        meta = ['サービス提供'] if category == 'コーディネート' else []
        tid = _new_task_id(existing_ids)
        existing_ids.add(tid)
        existing_gcal.add(gid)
        new_tasks.append(
            {
                'id': tid,
                'gcalId': gid,
                'spaceId': space_id,
                'category': category,
                'title': title,
                'detail': detail,
                'deadline': deadline,
                'status': '未',
                'tags': [],
                'metaTags': [],
                'subtasks': [],
                'createdAt': datetime.now().isoformat(),
            }
        )
    return new_tasks


def gcal_import(argv):
    """Googleカレンダーの予定を読み、MMSにまだない gcalId だけタスク追加。

    認証: tasks/.gcal-oauth/credentials.json（OAuth）または MMS_GCAL_CREDENTIALS（SA）

    使い方:
      python3 sync_mms.py gcal-import [--days 90] [--space 西岡慎也]
          [--category その他] [--dry-run]
    """
    import argparse

    parser = argparse.ArgumentParser(prog='sync_mms.py gcal-import')
    parser.add_argument('--days', type=int, default=90, help='これから何日分を取り込むか')
    parser.add_argument('--space', default='西岡慎也', help='MMSのスペース名')
    parser.add_argument('--category', default='その他', help='タイトルに「コーディネート」が無い場合のカテゴリ')
    parser.add_argument('--dry-run', action='store_true', help='Firebaseには書かず表示のみ')
    opts = parser.parse_args(argv)

    service, cal_id = _get_calendar_service_and_id()
    items = _fetch_calendar_events(service, cal_id, opts.days)
    print(f'Googleカレンダー: {len(items)} 件（{opts.days}日以内・開始時刻順）')

    print('MMS の最新データを取得中...')
    data = fetch_firebase()
    spaces = {s.get('name', ''): s.get('id', '') for s in data.get('spaces', [])}
    space_id = spaces.get(opts.space, '')
    if opts.space and not space_id:
        print(f'警告: スペース "{opts.space}" が見つかりません。利用可能: {", ".join(spaces.keys())}')
        sys.exit(1)

    new_tasks = _plan_gcal_import_tasks(
        data,
        space_id,
        items,
        default_category=opts.category,
        coordinate_only=False,
    )

    if not new_tasks:
        print('追加なし（すべて既にMMSに連携済み、または対象期間に予定がありません）。')
        return

    print(f'新規にMMSへ追加: {len(new_tasks)} 件')
    for t in new_tasks:
        print(f"  - {t.get('deadline') or '?'} | {t.get('title')} [{t.get('category')}]")

    if opts.dry_run:
        print('（--dry-run のため Firebase には書き込みません）')
        return

    data.setdefault('tasks', []).extend(new_tasks)
    print('Firebase に書き込み中...')
    save_firebase(data)
    print('完了。ローカルを更新します。')
    pull()


def sync_coordinate(argv):
    """西岡スペースに「コーディネート」カテゴリを確保し、
    Googleカレンダーからタイトルに「コーディネート」を含む予定だけMMSへ同期する。
    """
    import argparse

    parser = argparse.ArgumentParser(prog='sync_mms.py sync-coordinate')
    parser.add_argument('--days', type=int, default=90, help='これから何日分を取り込むか')
    parser.add_argument('--space', default='西岡慎也', help='MMSのスペース名')
    parser.add_argument(
        '--all-events',
        action='store_true',
        help='タイトルを絞らず全予定を取り込む（gcal-import と同様のカテゴリ推定）',
    )
    parser.add_argument('--dry-run', action='store_true', help='Firebaseには書かず表示のみ')
    opts = parser.parse_args(argv)

    service, cal_id = _get_calendar_service_and_id()
    items = _fetch_calendar_events(service, cal_id, opts.days)
    coord_only = not opts.all_events
    if coord_only:
        n_title = sum(1 for ev in items if 'コーディネート' in (ev.get('summary') or ''))
        print(
            f'Googleカレンダー: {len(items)} 件中、タイトルに「コーディネート」: {n_title} 件'
        )
    else:
        print(f'Googleカレンダー: {len(items)} 件（{opts.days}日以内）')

    print('MMS の最新データを取得中...')
    data = fetch_firebase()
    spaces = {s.get('name', ''): s.get('id', '') for s in data.get('spaces', [])}
    space_id = spaces.get(opts.space, '')
    if not space_id:
        print(f'エラー: スペース「{opts.space}」が見つかりません。')
        sys.exit(1)

    try:
        cat_changed = ensure_category_in_data(data, opts.space, 'コーディネート')
    except ValueError as e:
        print(e)
        sys.exit(1)
    if cat_changed:
        print(f'カテゴリ「コーディネート」を「{opts.space}」に追加します。')

    new_tasks = _plan_gcal_import_tasks(
        data,
        space_id,
        items,
        default_category='その他',
        coordinate_only=coord_only,
    )

    if not new_tasks and not cat_changed:
        print('追加なし（カテゴリも既存、カレンダーもすべて連携済み）。')
        return

    if new_tasks:
        print(f'新規にMMSへ追加: {len(new_tasks)} 件')
        for t in new_tasks:
            print(f"  - {t.get('deadline') or '?'} | {t.get('title')}")

    if opts.dry_run:
        print('（--dry-run のため Firebase には書き込みません）')
        return

    data.setdefault('tasks', []).extend(new_tasks)
    print('Firebase に書き込み中...')
    save_firebase(data)
    print('完了。ローカルを更新します。')
    pull()


# ─────────────────────────────────────────────
# ENSURE-CATEGORY: スペースのカテゴリ一覧にブロック用の名前を追加（Firebase）
# ─────────────────────────────────────────────

def ensure_category(argv):
    """MMSはスペースの categories に無い名前だとブロックが出ないことがある。
    指定カテゴリが無ければ挿入して Firebase に保存する。

    使い方:
      python3 sync_mms.py ensure-category [--space 西岡慎也] [--name コーディネート]
    """
    import argparse

    parser = argparse.ArgumentParser(prog='sync_mms.py ensure-category')
    parser.add_argument('--space', default='西岡慎也', help='スペース名')
    parser.add_argument('--name', default='コーディネート', help='追加するカテゴリ名')
    opts = parser.parse_args(argv)

    print('MMS の最新データを取得中...')
    data = fetch_firebase()
    try:
        changed = ensure_category_in_data(data, opts.space, opts.name)
    except ValueError as e:
        print(e)
        sys.exit(1)
    if not changed:
        print(f'「{opts.space}」には既に「{opts.name}」があります。変更なし。')
        pull()
        return
    print(f'「{opts.space}」の categories に「{opts.name}」を追加しました。')
    save_firebase(data)
    print('Firebase に反映済み。ローカルを更新します。')
    pull()


# ─────────────────────────────────────────────
# エントリーポイント
# ─────────────────────────────────────────────

USAGE = """
使い方:
  python3 sync_mms.py pull                         # MMS → mms-tasks.md（最新を取得）
  python3 sync_mms.py push                         # mms-tasks.md の変更 → MMS
  python3 sync_mms.py add "タスク名" [オプション]  # タスクを追加して即反映
    オプション: --space スペース名  --deadline YYYY-MM-DD  --category カテゴリ
  python3 sync_mms.py done <IDまたはタイトル>      # タスクを完了にして即反映
  python3 sync_mms.py list                         # タスク一覧を表示
  python3 sync_mms.py gcal-import [オプション]    # Googleカレンダー→MMS（未連携分のみ）
    要: MMS_GCAL_CREDENTIALS（またはGOOGLE_APPLICATION_CREDENTIALS）, MMS_GCAL_CALENDAR_ID
    オプション: --days 90  --space 西岡慎也  --category その他  --dry-run
  python3 sync_mms.py ensure-category [オプション]  # スペースのカテゴリ一覧にブロック名を追加
    オプション: --space 西岡慎也  --name コーディネート
  python3 sync_mms.py sync-coordinate [オプション]   # コーディネートカテゴリ確保 + カレンダー同期
    タイトルに「コーディネート」を含む予定のみ取り込み（--all-events で全件）
    認証: tasks/.gcal-oauth/credentials.json または MMS_GCAL_CREDENTIALS + MMS_GCAL_CALENDAR_ID
    オプション: --days 90  --space 西岡慎也  --dry-run  --all-events
"""

if __name__ == '__main__':
    if len(sys.argv) < 2:
        pull()
        sys.exit(0)

    mode = sys.argv[1]

    if mode == 'pull':
        pull()
    elif mode == 'push':
        push()
    elif mode == 'add':
        add_task(sys.argv[2:])
    elif mode == 'done':
        if len(sys.argv) < 3:
            print('使い方: python3 sync_mms.py done <IDまたはタイトルの一部>')
            sys.exit(1)
        done_task(sys.argv[2])
    elif mode == 'list':
        list_tasks()
    elif mode == 'gcal-import':
        gcal_import(sys.argv[2:])
    elif mode == 'ensure-category':
        ensure_category(sys.argv[2:])
    elif mode == 'sync-coordinate':
        sync_coordinate(sys.argv[2:])
    else:
        print(f'不明なコマンド: {mode}')
        print(USAGE)
        sys.exit(1)
