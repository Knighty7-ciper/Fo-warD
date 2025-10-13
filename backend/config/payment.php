<?php
/**
 * Local Payment Configuration for Forward LMS
 * Kenya-friendly payment integration (M-Pesa, Bank Transfer, Cash)
 */

class PaymentHandler {
    private $db;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    /**
     * Process M-Pesa payment
     */
    public function processMpesaPayment($phone_number, $amount, $reference, $user_id) {
        // Log payment attempt
        $sql = "INSERT INTO transactions (user_id, amount, payment_method, reference_number, status, created_at) 
                VALUES (:user_id, :amount, 'mpesa', :reference, 'pending', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':amount' => $amount,
            ':reference' => $reference
        ]);
        
        return [
            'success' => true,
            'transaction_id' => $this->db->lastInsertId(),
            'message' => 'Payment initiated. Please complete on your phone.',
            'reference' => $reference
        ];
    }
    
    /**
     * Process bank transfer
     */
    public function processBankTransfer($account_number, $amount, $reference, $user_id) {
        $sql = "INSERT INTO transactions (user_id, amount, payment_method, reference_number, status, created_at) 
                VALUES (:user_id, :amount, 'bank_transfer', :reference, 'pending', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':amount' => $amount,
            ':reference' => $reference
        ]);
        
        return [
            'success' => true,
            'transaction_id' => $this->db->lastInsertId(),
            'message' => 'Bank transfer recorded. Awaiting confirmation.',
            'bank_details' => $this->getBankDetails()
        ];
    }
    
    /**
     * Process cash payment
     */
    public function processCashPayment($amount, $receipt_number, $user_id, $admin_id) {
        $sql = "INSERT INTO transactions (user_id, amount, payment_method, reference_number, status, verified_by, created_at) 
                VALUES (:user_id, :amount, 'cash', :receipt, 'completed', :admin_id, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':amount' => $amount,
            ':receipt' => $receipt_number,
            ':admin_id' => $admin_id
        ]);
        
        return [
            'success' => true,
            'transaction_id' => $this->db->lastInsertId(),
            'message' => 'Cash payment recorded successfully.'
        ];
    }
    
    /**
     * Verify payment
     */
    public function verifyPayment($transaction_id, $admin_id) {
        $sql = "UPDATE transactions 
                SET status = 'completed', verified_by = :admin_id, verified_at = NOW() 
                WHERE id = :transaction_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':transaction_id' => $transaction_id,
            ':admin_id' => $admin_id
        ]);
    }
    
    /**
     * Get bank details for transfers
     */
    private function getBankDetails() {
        return [
            'bank_name' => 'Equity Bank Kenya',
            'account_name' => 'Forward LMS',
            'account_number' => '0123456789',
            'branch' => 'Nairobi Branch'
        ];
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($user_id, $limit = 50) {
        $sql = "SELECT * FROM transactions 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
