<?php
// Payment Helper Functions

require_once(__DIR__ . '/payment_gateway.php');

function processAppointmentPayment($appointment_id, $patient_id, $amount, $payment_method) {
    $payment_gateway = new PaymentGateway();
    
    // Process payment through gateway
    $payment_result = $payment_gateway->processPayment($amount, $payment_method);
    
    if ($payment_result['success']) {
        // Insert payment record
        $sql = "INSERT INTO payments (appointment_id, patient_id, amount, payment_method, transaction_id, status) 
                VALUES (?, ?, ?, ?, ?, 'completed')";
        $payment_id = insertData($sql, "iidsss", [
            $appointment_id, 
            $patient_id, 
            $amount, 
            $payment_method, 
            $payment_result['transaction_id']
        ]);
        
        if ($payment_id) {
            // Update appointment payment status
            $sql = "UPDATE appointments SET payment_status = 'completed' WHERE appointment_id = ?";
            $result = executeNonQuery($sql, "i", [$appointment_id]);
            
            if ($result) {
                return [
                    'success' => true,
                    'payment_id' => $payment_id,
                    'transaction_id' => $payment_result['transaction_id']
                ];
            }
        }
        
        return ['success' => false, 'error' => 'Failed to record payment'];
    }
    
    return ['success' => false, 'error' => $payment_result['error'] ?? 'Payment processing failed'];
}

function initiateRefund($payment_id) {
    // Get payment details
    $sql = "SELECT * FROM payments WHERE payment_id = ? AND status = 'completed'";
    $payment = fetchRow($sql, "i", [$payment_id]);
    
    if (!$payment) {
        return ['success' => false, 'error' => 'Invalid payment or already refunded'];
    }
    
    $payment_gateway = new PaymentGateway();
    $refund_result = $payment_gateway->initiateRefund($payment['transaction_id'], $payment['amount']);
    
    if ($refund_result['success']) {
        // Update payment status
        $sql = "UPDATE payments SET status = 'refunded' WHERE payment_id = ?";
        $result = executeNonQuery($sql, "i", [$payment_id]);
        
        if ($result) {
            // Update appointment payment status
            $sql = "UPDATE appointments SET payment_status = 'refunded' WHERE appointment_id = ?";
            executeNonQuery($sql, "i", [$payment['appointment_id']]);
            
            return [
                'success' => true,
                'refund_id' => $refund_result['refund_id'],
                'amount' => $payment['amount']
            ];
        }
    }
    
    return ['success' => false, 'error' => 'Failed to process refund'];
}

function getPaymentHistory($patient_id, $limit = 10) {
    $sql = "SELECT p.*, a.appointment_date, a.appointment_time,
            CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
            h.name as hospital_name
            FROM payments p
            JOIN appointments a ON p.appointment_id = a.appointment_id
            JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
            JOIN users u ON dp.user_id = u.user_id
            JOIN hospitals h ON a.hospital_id = h.hospital_id
            WHERE p.patient_id = ?
            ORDER BY p.payment_date DESC
            LIMIT ?";
    
    return fetchRows($sql, "ii", [$patient_id, $limit]);
}

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}