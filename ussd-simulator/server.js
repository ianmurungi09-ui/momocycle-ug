const express = require('express');
const bodyParser = require('body-parser');

const app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: false }));

const users = {};
const wasteLogs = [];

app.post('/ussd', (req, res) => {
    let { sessionId, serviceCode, phoneNumber, text } = req.body;
    let response = '';

    const textArray = text.split('*');
    const level = textArray.length;

    if (text === '') {
        if (!users[phoneNumber]) {
            response = 'CON Welcome to MoMoCycle UG\nPlease register:\n1. Household\n2. Waste Collector\n3. Aggregator';
        } else {
            response = 'CON Welcome back, ' + users[phoneNumber].role + '\n1. Log Waste Drop-off\n2. Check Balance / Payouts\n3. Exit';
        }
    } else if (textArray[0] === '1' || textArray[0] === '2' || textArray[0] === '3') {
        if (!users[phoneNumber]) {
            const roleMap = { '1': 'Household', '2': 'Collector', '3': 'Aggregator' };
            users[phoneNumber] = { role: roleMap[textArray[0]], registeredAt: new Date() };
            response = 'END Registration successful as ' + users[phoneNumber].role + '! Dial *165# again to start.';
        } else {
            if (textArray[1] === '1') {
                if (level === 2) {
                    response = 'CON Select Waste Type:\n1. Plastics\n2. Organic\n3. Metals';
                } else if (level === 3) {
                    response = 'CON Enter estimated weight (in KGs):';
                } else if (level === 4) {
                    const typeMap = { '1': 'Plastics', '2': 'Organic', '3': 'Metals' };
                    const wasteType = typeMap[textArray[2]];
                    const weight = textArray[3];
                    
                    wasteLogs.push({ phoneNumber, wasteType, weight, status: 'Pending Verification', timestamp: new Date() });
                    
                    response = 'END Success! Logged ' + weight + 'kg of ' + wasteType + '. Pending aggregator confirmation for MTN MoMo payout.';
                }
            } else if (textArray[1] === '2') {
                response = 'END Your MoMo instant payout wallet is linked. Total verified micro-incentives: UGX 0.';
            }
        }
    } else {
        response = 'END Invalid choice. Please try again.';
    }

    res.set('Content-Type: text/plain');
    res.send(response);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log('MoMoCycle USSD Simulator running on port ' + PORT);
});
