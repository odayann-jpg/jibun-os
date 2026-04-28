# CRM API化 依頼書

## 背景

AIで顧客状況を見える化するシステム（Notion + Claude）と既存CRMを連携するため、
既存のCSVエクスポート機能のJSON版APIを追加してほしいです。

ソース解析の結果、**既存csv()関数のCSV生成部分をJSON化するだけで完成する規模**です。
プログラマー作業時間の見積もり：**1〜2時間程度**。

---

## 解析で判明した既存実装

### MembersController::csv()（line 1576〜）

既存のCSV出力カラム：
- id, charge_id, name, kana, company, tel, tel_mb, zipcode, address, email
- is_billing, consulting_fee（MemberContractより）, is_service（同）
- payment_date, birthday, last_date
- last_max_lease_amount, last_available_amount
- memo

`recursive => 1` で MemberContract（契約マスタ）も取得している。

### PurchasesController::csv()（line 675〜）

既存のCSV出力カラム：
- date, contract_id, member_id, is_stock, brand_name, item_name
- size, color, cost, price, quantity, is_selection
- total, profit_rate, profit_amount, barcode, stock_id

`recursive => 0` で Member（顧客）も取得している。

---

## 依頼内容（具体）

### API 1: 顧客マスタ取得

```
GET /api/members.json?page=1&limit=100
GET /api/members/<id>.json
```

レスポンス例（既存csv関数の出力を JSON 化するだけ）：
```json
{
  "members": [
    {
      "id": 16,
      "charge_id": "西岡 慎也",
      "name": "北城 雅照",
      "kana": "キタシロ マサテル",
      "company": "医療法人社団 新潮会",
      "tel": "09033357570",
      "tel_mb": "09033357570",
      "zipcode": "150-0022",
      "address": "東京都渋谷区...",
      "email": "m.kitashiro@adachikeiyu.com",
      "is_billing": "会社宛",
      "birthday": null,
      "last_date": "2026-04-22",
      "last_max_lease_amount": 2400000,
      "last_available_amount": -158995,
      "memo": "...",
      "current_contract": {
        "consulting_fee": 300000,
        "is_service": "リフレーミング",
        "payment_date": "2026-11-30",
        "start_date": "2026-02-01",
        "end_date": "2027-01-31"
      }
    }
  ],
  "pagination": { "page": 1, "total": 101 }
}
```

### API 2: リースアイテム取得

```
GET /api/purchases.json?member_id=<id>&year=2026
GET /api/purchases.json?from=2026-01-01&to=2026-12-31
```

レスポンス例：
```json
{
  "purchases": [
    {
      "date": "2026-04-22",
      "contract_id": "4年目",
      "member_id": 16,
      "member_name": "北城 雅照",
      "is_stock": "在庫商品",
      "brand_name": "TH",
      "item_name": "MA-1",
      "size": "Free",
      "color": "Free",
      "cost": 39000,
      "price": 81000,
      "quantity": 1,
      "is_selection": "なし",
      "total": 81000,
      "profit_rate": 0.52,
      "profit_amount": 42000
    }
  ]
}
```

### 認証

APIキー認証（HTTPヘッダー `X-API-Key: xxx`）でOK。
シンプルなトークン認証で十分。

---

## 実装の最短ルート

`MembersController.php` と `PurchasesController.php` に既にある `csv()` メソッドを参考に、
新しい `json()` メソッドを追加するイメージ：

```php
public function json(){
    $params = array(
        'conditions' => am($this->conditions, $conditions),
        'order' => $this->order,
        'recursive' => 0,
    );
    $data = $this->{$this->modelClass}->find('all', $params);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'data' => $data,
    ), JSON_UNESCAPED_UNICODE);
    exit;
}
```

`csv()` のロジックをほぼそのまま使えます。

---

## ルーティング追加

`app/Config/routes.php` に：

```php
Router::connect('/api/members.json', array('controller' => 'members', 'action' => 'json'));
Router::connect('/api/members/:id.json', array('controller' => 'members', 'action' => 'json'));
Router::connect('/api/purchases.json', array('controller' => 'purchases', 'action' => 'json'));
```

---

## セキュリティ

- 既存のadmin認証と同じレベルの保護
- APIキーは環境変数または設定ファイルで管理
- CORS設定で許可するOriginを限定（必要なら）

---

## 工数見積もり（参考）

| 項目 | 工数 |
|---|---|
| Members API実装 | 30分 |
| Purchases API実装 | 30分 |
| ルーティング追加 | 10分 |
| APIキー認証 | 30分 |
| テスト | 30分 |
| **合計** | **約2時間** |

---

## 参考：既存ソースコード

ソース解析済み。以下のローカルパスに保存してあります：
- /Users/hirotaseiji/Desktop/自分OS/projects/crm-source/Controller/MembersController.php
- /Users/hirotaseiji/Desktop/自分OS/projects/crm-source/Controller/PurchasesController.php

---

## ご相談したいこと

1. 上記の API 追加は対応可能ですか？
2. APIキーの発行はどうしますか？
3. 工数・費用感を教えてください
4. （オプション）SalesController.php の売上データもAPIで取れると、入金管理にも使えます

よろしくお願いします。
