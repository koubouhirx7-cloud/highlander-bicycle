const express = require('express');
const path = require('path');
const createCheckoutSession = require('./api/create-checkout-session');

const app = express();
const PORT = process.env.PORT || 8000;

// Middleware to parse JSON bodies
app.use(express.json());

// Mount Serverless Function as Express Route
app.post('/api/create-checkout-session', createCheckoutSession);

// Serve static assets from the current directory
app.use(express.static(__dirname));

// Fallback for HTML routing (e.g. serving index.html if route not found)
app.get('*', (req, res) => {
    // Only serve index.html for text/html requests, otherwise let static middleware handle or return 404
    if (req.accepts('html')) {
        res.sendFile(path.join(__dirname, 'index.html'));
    } else {
        res.status(404).send('Not found');
    }
});

app.listen(PORT, () => {
    console.log(`===================================================`);
    console.log(` Highlander Bicycle Shop Local Server is running!`);
    console.log(` Preview URL: http://localhost:${PORT}`);
    console.log(`===================================================`);
});
