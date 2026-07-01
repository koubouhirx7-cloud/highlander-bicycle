# 移管作業ログ

## 2026-06-27

### 完了

- Xserver/PHP移管の実行プランを作成
- HubLink注文送信用のPHP APIを追加
- `.htaccess` にPHP APIルートを追加
- `.env.example` を追加
- microCMS取得用のPHP共通関数を追加
- ブログ/商品/制作ログ用のmicroCMS PHPプロキシを追加
- `robots.txt` を追加
- `sitemap.xml` を追加
- 管理/決済系ページに `X-Robots-Tag: noindex` を追加
- トップニュース、ブログ、商品一覧、商品詳細をmicroCMS PHPプロキシ経由に変更
- ショップページから不要な `config.js` 読み込みを削除
- `package.json` のbuildからmicroCMSキー出力を削除
- PHP簡易サーバー用の `dev-router.php` を追加
- CMS由来のタイトル/画像/カテゴリ表示をHTMLエスケープ

### 検証

- PHP文法チェック: OK
- JavaScript構文チェック: OK
- `package.json` JSON読み込み: OK
- sitemap XML読み込み: OK
- sitemap URL数: 39

### 未対応

- XserverへのSSH接続
- Xserver上のテスト公開場所作成
- Stripeテスト秘密鍵の設定
- HubLinkトークンの設定
- microCMS APIキーのXserver側設定
- Firebase用 `config.js` の扱い整理
- ブログ/商品詳細のSEO用PHPテンプレート化
- 制作ログページの作成
- Search Consoleへのsitemap送信
- GA4設置/確認
- note/Xへの実投稿

### 次にやること

1. XserverのSSH接続情報を確認
2. テスト公開場所を作る
3. `.env` をXserverに安全に設置
4. Stripeテスト決済を通す
5. Xserver上で `/api/microcms-products` と `/api/microcms-posts` の動作確認
6. ブログ/商品詳細をSEOに強いPHPテンプレートへ移行
