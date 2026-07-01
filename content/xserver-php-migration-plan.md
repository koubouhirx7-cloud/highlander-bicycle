# Xserver/PHP移管メモ

## 前提

ハイランダーのサイトは、今後Xserver上のPHPで動く基盤へ移していく。

目的は単なる引っ越しではなく、以下をまとめて整えること。

- SEOに強い公開ページ
- microCMSとの安全な連携
- ブログ・商品・制作ログの更新しやすさ
- note/Xなど外部発信との導線
- 決済・予約・CRM連携まわりの整理
- 自転車店とAI/Web制作の両方を支える情報基盤

## 現状メモ

現在は静的HTML中心。

ローカル確認ではNodeの簡易サーバーを使っている。

主な動的要素:

- microCMSの商品取得
- microCMSのブログ取得
- Stripe決済API
- HubLink/CRMへの注文送信
- Firebase系の会員・管理画面モック

注意点:

- `config.js` にmicroCMSやFirebaseの設定がブラウザから見える形で入っている
- `blog.html` と `index.html` でブログAPIを直接叩いている
- `shop.html` / `shop-detail.html` / `script.js` で商品APIを直接叩いている
- `api/create-checkout-session.php` はすでにPHP版がある
- `api/order-to-hublink.js` はPHP移管時に置き換え候補

## 移管後の基本設計

### 1. 公開ページはPHPテンプレート化

HTMLをいきなり全面CMS化せず、まずは既存デザインを活かしてPHPの共通パーツへ分ける。

- `header.php`
- `footer.php`
- `config.php`
- `functions.php`
- `api/`
- `templates/`

ナビ、フッター、CTA、メタ情報を共通化する。

### 2. microCMSはPHPサーバー側から取得

ブラウザにmicroCMS APIキーを出さない。

PHP側で取得して、必要なHTMLやJSONだけを返す。

対象:

- ブログ一覧
- ブログ詳細
- 商品一覧
- 商品詳細
- 制作ログ
- お知らせ

### 3. SEOは「ブラウザで後から描画」から「最初からHTMLにある」へ

現在のブログや商品一覧はJavaScriptで後から読み込む部分がある。

移管時は、検索エンジンやSNS共有で扱いやすいように、PHPで初期HTMLにタイトル・本文冒頭・画像・構造化データを出す。

整えるもの:

- title
- meta description
- canonical
- OGP
- Xカード
- JSON-LD
- パンくず
- sitemap.xml
- robots.txt

### 4. 制作ログを新しいコンテンツ基盤にする

「自転車屋だけど、AIとWebとUXもやっています」という立ち位置を広げるために、制作ログをサイト内に持つ。

microCMS側に `logs` のようなコンテンツタイプを作る想定。

項目案:

- タイトル
- 日付
- カテゴリ
- 概要
- 本文
- 関連画像
- 関連ページ
- note URL
- X投稿URL

### 5. note/Xとの役割分担

サイト:

- 信頼の着地点
- サービス説明
- 制作ログの蓄積
- 問い合わせ導線

note:

- 長文ストーリー
- 思想や背景
- 制作過程のまとめ

X:

- 日々の気づき
- 制作途中の小さな発見
- noteや制作ログへの入口

### 6. PHPで持ちたいAPI

優先度高:

- `api/microcms-posts.php`
- `api/microcms-products.php`
- `api/create-checkout-session.php`
- `api/order-to-hublink.php`

必要に応じて:

- `api/contact.php`
- `api/newsletter.php`
- `api/logs.php`

## 移行ステップ案

### Phase 1: 棚卸し

- 現在のページ一覧を整理
- microCMSで使っているコンテンツタイプを確認
- 公開ドメイン、Xserverの設置場所、PHPバージョン、SSL設定を確認
- 不要なモック・古いAPI・露出しているキーを整理

### Phase 2: PHP基盤を作る

- 共通ヘッダー/フッター化
- `.env` またはXserver側の設定でAPIキーを管理
- microCMS取得用のPHP関数を作る
- キャッシュ方針を決める

### Phase 3: SEO対応ページから移す

優先順:

1. トップページ
2. WEB部/Ride & Talkページ
3. ブログ一覧・詳細
4. 商品一覧・詳細
5. 制作ログ一覧・詳細

### Phase 4: 外部発信とつなぐ

- note記事からサイトの制作ログへリンク
- X固定ポストからWEB部ページへリンク
- サイト内にnote/X導線を設置
- OGP画像を整える

### Phase 5: 公開前チェック

- スマホ表示
- 表示速度
- 404/リダイレクト
- sitemap
- Google Search Console
- OGP表示
- microCMSキー非公開
- 決済テスト
- 注文/予約/CRM連携

## 重要な方針

Xserver移管では「今の見た目をそのまま置く」だけで終わらせない。

ハイランダーの自転車店としての信頼と、AI/Web/UXの新しい発信を同じ基盤で育てる。

特にWEB部は、単なるサービスページではなく、制作ログ・note・X投稿が循環する発信基地として設計する。
