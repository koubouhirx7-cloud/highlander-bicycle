# Xserver/PHP移管 実行プラン

## 0. 秘密鍵とログイン方針

秘密鍵の中身はチャットに貼らない。

PC内にある鍵ファイルを使う。

確認済み:

- `~/.ssh/xserver_bigrock` が存在
- SSHエージェントには未登録

使い方の候補:

1. `ssh-agent` に一時登録する
2. `~/.ssh/config` にXserver用の接続設定を書く
3. デプロイ時だけ `ssh -i ~/.ssh/xserver_bigrock ...` のように鍵ファイルを指定する

環境変数で渡す場合も、秘密鍵の本文ではなく `SSH_KEY_PATH=~/.ssh/xserver_bigrock` のように「鍵の場所」を渡すのが安全。

必要な情報:

- SSHホスト名
- SSHユーザー名
- SSHポート
- Xserver上の公開ディレクトリ
- 本番ドメイン
- テスト用サブドメインを使うかどうか

## 1. 最初にやること

### 1-1. SSH接続確認

目的:

- Xserverへ安全に入れるか確認
- 公開ディレクトリを確認
- PHPバージョンを確認

やること:

- 鍵を使ってSSH接続
- `public_html` 相当の場所を確認
- PHP/Apache/cURLが使えるか確認

### 1-2. テスト公開場所を作る

本番をいきなり上書きしない。

候補:

- `test.high-lander2.com`
- `dev.high-lander2.com`
- 既存ドメイン配下の一時ディレクトリ

## 2. PHP基盤

### 2-1. 既存HTMLをそのまま動かす

まずは現在の静的HTML/CSS/JS/imagesをXserverへ置いて、表示が崩れないか確認する。

### 2-2. APIをPHP化

優先:

- `api/create-checkout-session.php`
- `api/order-to-hublink.php`

追加済み:

- `api/order-to-hublink.php`
- `.htaccess` に `/api/order-to-hublink` のルート追加
- `.env.example`

本番で必要:

- `.env` にStripe/HubLink/microCMSの秘密値を入れる
- `.env` は公開されない場所、または`.htaccess`でアクセス禁止

## 3. ネットショップ/Stripe

目的:

- ピンバッジ、キーホルダー、限定グッズなどを販売できる状態にする
- カートからStripe決済まで通す
- 注文情報をHubLinkへ送る

現在の状態:

- カートはlocalStorageで動く
- Stripe Checkoutへ送るPHP APIは存在
- Stripe秘密鍵未設定時はモック画面へ逃がす
- HubLinkはNode版があり、PHP版を追加済み

次にやること:

1. Stripeテスト秘密鍵をXserverの `.env` に入れる
2. microCMSの商品データから商品一覧を取得
3. 商品をカートへ追加
4. Stripeテスト決済を実行
5. 成功画面へ戻る
6. HubLink送信結果を確認
7. 本番キーへ切り替える前にテストカードで検証

## 4. microCMS

目的:

- 商品、ブログ、制作ログを管理しやすくする
- APIキーをブラウザに出さない
- SEOに強いHTMLで表示する

使うアカウント:

- 既存のハイランダーブログ投稿アカウントでよい
- ただし権限とAPIキーの扱いは確認する

必要なコンテンツ:

- `blogs`
- `products`
- `logs`

次にやること:

1. microCMS管理画面でAPI名とスキーマを確認
2. PHPからmicroCMSを読む関数を作る
3. ブログ一覧/詳細をPHP化
4. 商品一覧/詳細をPHP化
5. 制作ログ一覧/詳細を新設

## 5. note/X投稿

方針:

- noteは長文ストーリー
- Xは日々の気づき
- サイトは信頼の着地点

進め方:

1. noteへGoogleログイン
2. 下書き記事を貼る
3. 公開前に本人確認
4. Xアカウントを確認
5. プロフィールと固定ポストを整える
6. note公開後にXへ投稿

注意:

- GoogleログインはAI操作ブラウザで弾かれることがある
- Chrome上で本人がログイン補助するのが現実的
- 公開ボタンは必ず本人確認後

## 6. Search Console / Analytics

目的:

- 新サイトの検索状態を見えるようにする
- sitemapを送る
- 移管後の検索流入とクリックを確認する

やること:

1. 既存プロパティを確認
2. 新URL構成を決める
3. `sitemap.xml` を生成
4. `robots.txt` を用意
5. Search Consoleでsitemap送信
6. GA4を確認/設置
7. OGP表示を確認

## 7. Firebase

現在:

- 会員登録/ログイン/管理画面のコードがある
- モック動作も残っている

方針:

- ネットショップ初期公開では必須にしない
- まずはゲスト購入を成立させる
- 会員機能は後で整理

理由:

- 決済/商品/注文導線の方が優先度が高い
- 会員機能を先に固めると公開までの距離が伸びる

## 8. 優先順位

1. SSH接続確認
2. Xserverテスト公開場所の作成
3. 既存サイトをそのまま配置
4. PHP APIの動作確認
5. Stripeテスト決済
6. microCMS商品取得をPHP経由化
7. sitemap/robots/Search Console
8. WEB部ページと制作ログ
9. note/X初回投稿
10. 本番切り替え

## 9. 今すぐ必要な確認事項

- XserverのSSH接続情報
- どのドメイン/サブドメインでテストするか
- Stripeのテスト秘密鍵をどこで確認するか
- HubLinkのショップトークンがあるか
- microCMSの管理画面に入れるか
- Xアカウントの有無
