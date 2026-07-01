# HighLander WEB部 LP ワイヤーフレーム / Figma引き継ぎメモ

このページは、1枚画像を切り貼りするLPではなく、後から編集できるHTMLコンポーネントを中心に組む。

## 基本方針

- 重要な文章、料金、制作事例、FAQはHTMLで管理する。
- SVGは装飾・図解・雰囲気づくりに限定する。
- 事例カードは後から差し替える可能性が高いため、画像化しない。
- 「ホームページ制作」を入口に置き、必要に応じて予約、LINE、管理画面、AI活用へ広げる。
- 月額制は現時点で固定商品にせず、保守・改善が必要な場合だけ個別見積もりにする。

## LP構成

1. Hero
   - 見出し: ホームページ制作から、小さなWebの仕組みまで。
   - 役割: ホームページ制作もできることを最初に伝える。
   - SVG: `images/webdept-hero-map.svg`

2. できることカード
   - ホームページ制作
   - LP・告知ページ
   - 予約・問い合わせ導線
   - 小さなWebの仕組み

3. 初心者向け導入
   - Webのことが分からない人でも相談できる入口として説明する。

4. 作れるもの
   - ホームページ・LP
   - 問い合わせフォーム
   - 予約導線
   - LINE連携
   - 受付・受注の管理
   - AI活用

5. AIを使った打ち合わせ・試作
   - 2〜3時間の打ち合わせで、画面や流れのたたき台を作れることを説明する。
   - 完成品ではなく初期案であることを明記する。
   - SVG: `images/webdept-process-flow.svg`

6. 進め方
   - 話を聞く
   - 見える形にする
   - 必要なものを作る
   - 公開後に整える

7. Ride & Talk
   - 主サービスではなく、考えを整理する手段のひとつとして扱う。

8. HubLink Cycle
   - 自転車店発の実践例として紹介する。
   - 他業種にも考え方を応用できることを示す。

9. 料金プレビュー
   - 入口だけをLPに表示する。
   - 詳細は `web-pricing.html` に分ける。

10. 制作事例
    - 画像化せず、HTMLのカード形式で編集可能にする。

11. FAQ / 安心材料
    - AIだけで自動生成ではないこと、成果保証ではないこと、月額契約必須ではないことを明記する。

## 実装ファイル

- `ride-and-talk.html`: WEB部LP本体
- `web-pricing.html`: 料金の目安ページ
- `style.css`: WEB部専用LPスタイル
- `images/webdept-hero-map.svg`: ヒーロー図解
- `images/webdept-process-flow.svg`: 進行フロー図解

## Figma化する場合

Figmaでは以下の粒度でフレーム化するとよい。

- Hero
- Service Cards
- Beginner Intro
- Capability Cards
- AI Prototype Flow
- Process
- Ride & Talk
- HubLink Cycle
- Pricing Preview
- Case Studies
- FAQ
- CTA

Figmaに取り込む場合も、制作事例と料金は画像化せず、テキストレイヤーとカードコンポーネントで作る。
