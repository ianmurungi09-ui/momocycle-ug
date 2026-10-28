const express = require('express');
const momoService = require('./momoService');
console.log('DEBUG - Loaded momoService exports:', momoService);

const { sendMoMoPayout, checkTransactionStatus } = momoService;

const app = express();
const PORT = process.env.PORT || 3000;

app.use(express.json());

app.post('/api/payouts', async (req, res) => {
    const { amount, payeePhoneNumber, externalId, note } = req.body;

    if (!amount || !payeePhoneNumber || !externalId) {
        return res.status(400).json({
            success: false,
            error: 'Missing required fields: amount, payeePhoneNumber, and externalId are required.'
        });
    }

    const result = await sendMoMoPayout(amount, payeePhoneNumber, externalId, note);

    if (result.success) {
        return res.status(200).json({
            success: true,
            message: 'Micro-payout initiated successfully.',
            referenceId: result.referenceId,
            status: result.status
        });
    } else {
        return res.status(500).json({
            success: false,
            error: result.error
        });
    }
});

app.get('/api/payouts/:referenceId', async (req, res) => {
    const { referenceId } = req.params;
    const result = await checkTransactionStatus(referenceId);

    if (result.success) {
        return res.status(200).json({
            success: true,
            transaction: result.data
        });
    } else {
        return res.status(500).json({
            success: false,
            error: result.error
        });
    }
});

app.listen(PORT, () => {
    console.log('MoMoCycle UG Backend running on port ' + PORT);
});
