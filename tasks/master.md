# タスク全体ビュー

> Vault 内すべての `- [ ] タスク` 形式の行を Tasks プラグインで集約。
> 個別タスクは各ファイル（Daily Note や `tasks/` 配下）で書き、ここでは見るだけ。
> チェックは各タスクの `[ ]` をクリックで完了。

## 期限切れ・今日

```tasks
not done
due on or before today
sort by due
```

## 今週（明日〜7日先）

```tasks
not done
due after today
due before in 7 days
sort by due, priority
```

## 期限なし（バックログ）

```tasks
not done
no due date
sort by priority
```

## 領域別（タグでグループ）

```tasks
not done
group by tags
sort by due, priority
```

## 最近完了（過去7日）

```tasks
done
done after today minus 7 days
sort by done reverse
```

---

## 書き方メモ

```
- [ ] タスク名 📅 2026-05-13 ⏫ #スタクロ
```

| 記号 | 意味 |
|---|---|
| `📅 2026-05-13` | 期限 |
| `⏫` | 高優先度 |
| `🔼` | 中優先度 |
| `🔽` | 低優先度 |
| `#タグ` | 領域タグ（`#スタクロ` `#やまとこ` `#GMC` `#G&T` `#西岡事業` `#自分OS` 等）|

完了する時は `- [x]` にする（Obsidian で `[ ]` クリックでも自動）。
