// api/order-to-hublink.js
// ブラウザからの注文を受け取り、サーバー側に保持した秘密鍵を付けて
// HubLink CRM (api/shop_order.php) へサーバー間で転送する。
//
// 設計意図:
//   - 秘密鍵(HUBLINK_SHOP_TOKEN)とテナントID(HUBLINK_TENANT_ID)は
//     Vercelの環境変数のみで管理し、ブラウザには絶対に出さない。
//   - ブラウザは自分のドメインの本関数だけを叩く（HubLinkを直接叩かせない）。
//   - 疎結合: HubLink連携が失敗しても 200 を返し、購入フローは止めない。

module.exports = async (req, res) => {
    if (req.method !== 'POST') {
        res.setHeader('Allow', 'POST');
        return res.status(405).json({ error: 'Method Not Allowed' });
    }

    const endpoint = process.env.HUBLINK_INGEST_URL || 'https://hub-link.jp/api/shop_order.php';
    const token = process.env.HUBLINK_SHOP_TOKEN;
    const tenantId = process.env.HUBLINK_TENANT_ID || '1';

    // 秘密鍵未設定なら連携をスキップ（購入は成立させる）
    if (!token) {
        console.warn('HUBLINK_SHOP_TOKEN is not set. Skipping CRM ingest.');
        return res.status(200).json({ ok: false, skipped: true });
    }

    try {
        const { order_id, customer, items, payment_method, payment_status } = req.body || {};

        // テナントIDはサーバー側で固定（ブラウザからの詐称を防ぐ）
        const payload = {
            tenant_id: tenantId,
            order_id,
            customer,
            items,
            payment_method,
            payment_status,
        };

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 6000);

        const r = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Shop-Token': token,
            },
            body: JSON.stringify(payload),
            signal: controller.signal,
        });
        clearTimeout(timeout);

        const data = await r.json().catch(() => ({}));
        return res.status(200).json({ ok: r.ok, hublink: data });
    } catch (error) {
        console.error('HubLink ingest failed:', error);
        // 失敗しても購入は成立させる（疎結合）
        return res.status(200).json({ ok: false, error: 'ingest_failed' });
    }
};
