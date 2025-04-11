<?php
// Payment Gateway Integration

class PaymentGateway {
    private $api_key;
    private $merchant_id;
    private $environment;
    
    public function __construct($environment = 'test') {
        $this->environment = $environment;
        $this->api_key = $environment === 'test' ? 'test_api_key' : 'live_api_key';
        $this->merchant_id = $environment === 'test' ? 'test_merchant' : 'live_merchant';
    }
    
    public function processPayment($amount, $payment_method, $currency = 'INR') {
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            return ['success' => false, 'error' => 'Invalid amount'];
        }
        
        // Validate payment method
        if (!in_array($payment_method, ['upi', 'card', 'net_banking'])) {
            return ['success' => false, 'error' => 'Invalid payment method'];
        }
        
        // Generate unique transaction ID
        $transaction_id = 'TXN' . time() . rand(1000, 9999);
        
        // In a real implementation, this would make API calls to the payment gateway
        // For demo purposes, we'll simulate a successful payment
        $payment_response = $this->simulatePaymentGatewayResponse($amount, $payment_method, $transaction_id);
        
        return $payment_response;
    }
    
    private function simulatePaymentGatewayResponse($amount, $payment_method, $transaction_id) {
        // Simulate processing delay
        usleep(500000); // 0.5 seconds
        
        // Simulate success response
        return [
            'success' => true,
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'currency' => 'INR',
            'payment_method' => $payment_method,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'completed'
        ];
    }
    
    public function verifyPayment($transaction_id) {
        // In a real implementation, this would verify the payment status with the payment gateway
        // For demo purposes, we'll always return success
        return [
            'success' => true,
            'transaction_id' => $transaction_id,
            'status' => 'completed',
            'verification_timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    public function initiateRefund($transaction_id, $amount) {
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            return ['success' => false, 'error' => 'Invalid refund amount'];
        }
        
        // In a real implementation, this would initiate a refund through the payment gateway
        // For demo purposes, we'll simulate a successful refund
        return [
            'success' => true,
            'refund_id' => 'REF' . time() . rand(1000, 9999),
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'status' => 'completed',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}