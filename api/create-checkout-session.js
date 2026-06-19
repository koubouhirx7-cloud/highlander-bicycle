const stripeLib = require('stripe');

module.exports = async (req, res) => {
    // Only allow POST
    if (req.method !== 'POST') {
        res.setHeader('Allow', 'POST');
        return res.status(455).json({ error: 'Method Not Allowed' });
    }

    try {
        const { cart } = req.body;
        if (!cart || !Array.isArray(cart) || cart.length === 0) {
            return res.status(400).json({ error: 'Cart is empty or invalid' });
        }

        // Calculate total amount in case we need to fallback
        const totalAmount = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        // Check Stripe secret key
        const stripeSecretKey = process.env.STRIPE_SECRET_KEY;
        if (!stripeSecretKey || stripeSecretKey === 'undefined') {
            console.warn('STRIPE_SECRET_KEY environment variable is not set. Falling back to mockup payment screen.');
            return res.status(200).json({
                url: `/mock-stripe-checkout.html?amount=${totalAmount}`
            });
        }

        const stripe = stripeLib(stripeSecretKey);

        // Construct line items for Stripe Checkout
        const lineItems = cart.map(item => ({
            price_data: {
                currency: 'jpy',
                product_data: {
                    name: item.name,
                },
                unit_amount: Math.round(item.price), // Must be integer
            },
            quantity: item.quantity,
        }));

        // Get origin from headers to support localhost or any deployed URL
        const origin = req.headers.origin || `${req.headers['x-forwarded-proto'] || 'http'}://${req.headers.host}`;

        // Create Stripe checkout session
        const session = await stripe.checkout.sessions.create({
            payment_method_types: ['card'],
            line_items: lineItems,
            mode: 'payment',
            success_url: `${origin}/order-success.html?session_id={CHECKOUT_SESSION_ID}`,
            cancel_url: `${origin}/checkout.html`,
        });

        console.log(`Stripe session created successfully: ${session.id}`);
        return res.status(200).json({ url: session.url });

    } catch (error) {
        console.error('Error creating Stripe session:', error);
        return res.status(500).json({ error: 'Internal Server Error', details: error.message });
    }
};
