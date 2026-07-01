# ネットショップ MySQL注文DB実装ログ

作成日: 2026-07-02

## 決定

Highlanderネットショップの注文履歴・配送先情報は、XサーバーのMySQLに保存できる構成へ進める。

## なぜMySQLを使うのか

- ブラウザ保存だけでは、別端末で注文履歴を見られない。
- ブラウザのデータ削除で注文控えが消える。
- 店舗側で注文履歴、配送先、決済状況を確実に追いにくい。
- 顧客管理v2と将来つなぐ場合、サーバー側に注文データがある方が安全に扱える。

## 今回の実装方針

- 既存のJSONファイル保存を急にやめず、MySQLが設定されていればDBにも保存する。
- MySQL未設定でも購入フローが止まらないように、従来のJSON保存を退避先として残す。
- Stripe注文、銀行振込注文、PayPayデモ注文を同じ保存関数へ通す。
- 顧客管理v2とは今すぐ直結せず、ネットショップDBを注文履歴の正式な控えとして使う。

## 作ったもの

- `includes/shop-db.php`
  - MySQL接続
  - 顧客の作成・更新
  - 注文の作成・更新
  - 注文明細の保存

- `includes/order-storage.php`
  - 既存の注文保存関数をMySQL対応に拡張
  - DBが使えない場合はJSON保存へフォールバック

- `api/order-to-hublink.php`
  - 銀行振込・PayPayデモなどStripe以外の注文もサーバー側へ保存
  - HubLink連携が失敗しても注文保存自体は止めない

- `docs/highlander-shop-mysql-schema.sql`
  - XサーバーMySQLへ作成するテーブル定義

## 必要な環境変数

- `SHOP_DB_HOST`
- `SHOP_DB_NAME`
- `SHOP_DB_USER`
- `SHOP_DB_PASSWORD`
- `SHOP_DB_CHARSET`

## 次に必要な作業

1. XサーバーのサーバーパネルでMySQLデータベースとユーザーを作る。
2. `docs/highlander-shop-mysql-schema.sql` をphpMyAdminなどで実行する。
3. Xサーバーの `.env` に `SHOP_DB_*` を設定する。
4. テスト注文を1件入れて、`shop_customers`、`shop_orders`、`shop_order_items` に保存されるか確認する。
