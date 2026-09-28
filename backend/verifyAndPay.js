const { sendMoMoPayout } = require('./momoService');

const sampleCompletedDropOff = {
    logId: 'LOG-9842',
    collectorPhoneNumber: '256771234567',
    wasteType: 'Plastics',
    weightKg: 10,
    ratePerKg: 200
};

async function simulateAggregatorVerificationAndPayout() {
    console.log('--- MoMoCycle UG: Aggregator Verification & Payout Trigger ---');
    console.log('Processing drop-off ' + sampleCompletedDropOff.logId + ' for collector: ' + sampleCompletedDropOff.collectorPhoneNumber);
    
    const totalPayoutAmount = sampleCompletedDropOff.weightKg * sampleCompletedDropOff.ratePerKg;
    console.log('Calculated Incentive: UGX ' + totalPayoutAmount + ' for ' + sampleCompletedDropOff.weightKg + 'kg of ' + sampleCompletedDropOff.wasteType);

    console.log('Initiating MTN MoMo micro-payout request...');
    
    const result = await sendMoMoPayout(
        totalPayoutAmount,
        sampleCompletedDropOff.collectorPhoneNumber,
        sampleCompletedDropOff.logId,
        'MoMoCycle Reward: ' + sampleCompletedDropOff.weightKg + 'kg ' + sampleCompletedDropOff.wasteType
    );

    if (result.success) {
        console.log('SUCCESS: Payout dispatched to mobile wallet!');
        console.log('Transaction Reference ID:', result.referenceId);
    } else {
        console.log('FAILED: Payout could not be processed.');
        console.log('Reason:', result.error);
    }
}

simulateAggregatorVerificationAndPayout();
