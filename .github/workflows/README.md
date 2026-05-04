# GitHub Actions: 自動デプロイ

main へ push されると、各プロジェクトの workflow が paths フィルタで該当変更を検出して、Xserver に FTP デプロイします。

## ファイル構成

| Workflow | プロジェクト | 本番URL |
|---|---|---|
| `deploy-stylist-closet.yml` | stylist-closet（スタクロ吉祥寺） | https://fashion-stylist.co.jp/closet/ |
| `deploy-stylist-photo.yml` | stylist-photo（スタイリストフォト） | https://fashion-stylist.co.jp/closet/photo/ |
| `deploy-lsic.yml` | lsic | https://fashion-stylist.co.jp/lsic/ |
| `deploy-give-take-management.yml` | give-take-management | https://giveandtake-japan.org/ |

**`gentle-space` は本リポ管理外**: `projects/gentle-space/` は `.gitignore` で意図的に除外され、別リポジトリで管理されている。本リポから自動デプロイは設定しない（誤って Secrets を設定すると本番ファイルが大量削除される危険があるため）。gentle-space に同等の仕組みが必要なら、gentle-space を管理するリポジトリ側に同様の workflow を別途作成する。

## 必要な GitHub Secrets（初回のみ設定）

リポジトリ Settings → **Secrets and variables → Actions → New repository secret** から、以下をすべて登録します。値はローカルの `.ftpconfig` と完全一致させてください。

### fashion-stylist.co.jp 系（スタクロ・フォト・LSIC で共通）
| Secret 名 | 内容 | 例 |
|---|---|---|
| `FSJ_FTP_HOST` | FTPホスト | `sv****.xserver.jp` または `fashion-stylist.co.jp` |
| `FSJ_FTP_USERNAME` | FTPユーザー名（サーバーID） | `fashionstylist` 等 |
| `FSJ_FTP_PASSWORD` | FTPパスワード | （Xserverサーバーパネルで設定したFTPパスワード） |
| `FSJ_CLOSET_FTP_REMOTE` | スタクロのリモートパス | `/fashion-stylist.co.jp/public_html/closet/` |
| `FSJ_PHOTO_FTP_REMOTE` | フォトのリモートパス | `/fashion-stylist.co.jp/public_html/closet/photo/` |
| `FSJ_LSIC_FTP_REMOTE` | LSICのリモートパス | `/fashion-stylist.co.jp/public_html/lsic/` |

### giveandtake-japan.org（Give & Take マネジメント）
| Secret 名 | 内容 |
|---|---|
| `GTM_FTP_HOST` | FTPホスト（例: `giveandtake-japan.xsrv.jp`） |
| `GTM_FTP_USERNAME` | FTPユーザー名 |
| `GTM_FTP_PASSWORD` | FTPパスワード |
| `GTM_FTP_REMOTE` | リモートパス（例: `/giveandtake-japan.org/public_html/`） |

合計 **10 個の Secret** を登録すれば、4プロジェクトすべてが git push で自動反映されます。

| グループ | 件数 |
|---|---|
| FSJ_*（fashion-stylist.co.jp 系・3プロジェクト共通） | 6 |
| GTM_*（giveandtake-japan.org） | 4 |
| **合計** | **10** |

## 設定手順

1. GitHub リポジトリ画面で `Settings` をクリック
2. 左メニュー `Secrets and variables` → `Actions`
3. 緑の `New repository secret` ボタン
4. **Name** と **Secret** を入力 → `Add secret`
5. 上の表のすべてを順番に追加

### 値の取り方
- 各プロジェクトの `.ftpconfig` ファイル（`projects/<project>/.ftpconfig`）に書いてある値をそのまま転記
- `.ftpconfig` は `.gitignore` 済みで誠司の手元にしかない
- もし `.ftpconfig` を紛失したら、Xserver の **サーバーパネル → FTPアカウント設定** で再取得 or 再設定

## 動作確認

Secrets 設定が完了したら:

1. リポジトリ画面の **Actions** タブを開く
2. 左の workflow 一覧から例えば `deploy-stylist-closet` を選択
3. 右上の `Run workflow` ボタンで手動実行
4. ジョブが緑チェックで完了すれば成功
5. 本番URLにアクセスして反映を確認

## トラブルシュート

| 症状 | 原因と対処 |
|---|---|
| `530 Login authentication failed` | Secret の値を再確認。ホスト・ユーザー・パスワードのいずれかが間違っている |
| `RETR command failed: 550 ファイルが存在しません` | server-dir のリモートパスが間違っている。`.ftpconfig` の `FTP_REMOTE` と完全一致しているか確認 |
| Workflow が走らない | 該当 workflow の `paths` フィルタにマッチする変更がない可能性。手動実行 (`Run workflow`) で動作確認 |
| `connection timed out` | Xserver側のIP制限が有効になっていないか確認（サーバーパネル → 国外IPアクセス制限設定）。GitHub Actions の runner は海外IPなので、国外IP制限を OFF にする必要あり |

## 既存の deploy.sh / build_public.sh との関係

各プロジェクトのローカルスクリプト（`projects/<project>/deploy.sh` `projects/stylist-closet/build_public.sh`）は**残しておきます**。理由:
- ネットワーク不調や GitHub Actions のサービス障害時の手動デプロイ手段として有用
- ローカルで動作確認したい時に便利

通常運用では git push で自動反映されるので、deploy.sh の手動実行は不要です。
