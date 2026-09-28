const crypto = require('crypto');
const axios = require('axios');

const SUBSCRIPTION_KEY = 'f1521d93ac184ef3871ca45e16275bf8';
const API_USER = 'aa35fbb1-317e-4d43-8e46-857140afda66';
const API_KEY = 'ca2f93109f164524a1314d51f3c26bd9';
const TARGET_ENVIRONMENT = 'sandbox';
const BASE_URL = 'https://sandbox.momodeveloper.mtn.com/disbursement';

const momoClient = axios.create({
    baseURL: BASE_URL,
    headers: {
        'Ocp-Apim-Subscription-Key': SUBSCRIPTION_KEY,
        'Connection': 'close'
    }
});

async function getMoMoToken() {
    const credentials = Buffer.from(API_USER + ':' + API_KEY).toString('base64');
    const response = await momoClient.post('/token/', {}, {
        headers: {
            'Authorization': 'Basic ' + credentials,
            'Content-Length': 0
        }
    });
    return response.data.access_token;
}

async function sendMoMoPayout(amount, payeePhoneNumber, externalId, note) {
    try {
        const token = await getMoMoToken();
        const referenceId = crypto.randomUUID();

        const payload = {
            amount: amount.toString(),
            currency: 'UGX',
            externalId: externalId,
            payee: {
                partyIdType: 'MSISDN',
                partyId: payeePhoneNumber
            },
            payerMessage: note || 'MoMoCycle Green Incentive Payout',
            payeeNote: 'Thank you for recycling with MoMoCycle UG!'
        };

        await momoClient.post('/v1_0/transfer', payload, {
            headers: {
                'Authorization': 'Bearer ' + token,
                'X-Reference-Id': referenceId,
                'X-Target-Environment': TARGET_ENVIRONMENT
            }
        });

        console.log('Payout transfer requested successfully. Ref ID:', referenceId);
        return { success: true, referenceId, status: 'PENDING' };
    } catch (error) {
        const errorMsg = error.response ? JSON.stringify(error.response.data) : error.message;
        console.error('Payout Error Caught:', errorMsg);
        return { success: false, error: errorMsg };
    }
}

async function checkTransactionStatus(referenceId) {
    try {
        const token = await getMoMoToken();
        const response = await momoClient.get('/v1_0/transfer/' + referenceId, {
            headers: {
                'Authorization': 'Bearer ' + token,
                'X-Target-Environment': TARGET_ENVIRONMENT
            }
        });
        return { success: true, data: response.data };
    } catch (error) {
        const errorMsg = error.response ? JSON.stringify(error.response.data) : error.message;
        console.error('Status Error Caught:', errorMsg);
        return { success: false, error: errorMsg };
    }
}

module.exports = { sendMoMoPayout, checkTransactionStatus };
