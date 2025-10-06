<?php

class StripeIntegration {
    private $api_key = '';
    private $publishable_key = '';
    private $base_url = 'https://api.stripe.com/v1';

    public function __construct() {
    }

    public function createPaymentIntent($amount, $currency, $metadata = []) {
        if (empty($this->api_key)) {
            return $this->mockCreatePaymentIntent($amount, $currency, $metadata);
        }

        return null;
    }

    private function mockCreatePaymentIntent($amount, $currency, $metadata) {
        $intent_id = 'pi_mock_' . bin2hex(random_bytes(12));

        return [
            'id' => $intent_id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'requires_payment_method',
            'client_secret' => $intent_id . '_secret_' . bin2hex(random_bytes(16)),
            'metadata' => $metadata,
            'created' => time()
        ];
    }

    public function confirmPayment($payment_intent_id) {
        if (empty($this->api_key)) {
            return $this->mockConfirmPayment($payment_intent_id);
        }

        return null;
    }

    private function mockConfirmPayment($payment_intent_id) {
        return [
            'id' => $payment_intent_id,
            'status' => 'succeeded',
            'amount_received' => 0,
            'charges' => [
                'data' => [
                    [
                        'id' => 'ch_mock_' . bin2hex(random_bytes(12)),
                        'paid' => true,
                        'receipt_url' => 'https://stripe.com/receipts/mock'
                    ]
                ]
            ]
        ];
    }

    public function createCustomer($email, $name, $metadata = []) {
        if (empty($this->api_key)) {
            return $this->mockCreateCustomer($email, $name, $metadata);
        }

        return null;
    }

    private function mockCreateCustomer($email, $name, $metadata) {
        return [
            'id' => 'cus_mock_' . bin2hex(random_bytes(12)),
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata,
            'created' => time()
        ];
    }

    public function createRefund($payment_intent_id, $amount = null, $reason = null) {
        if (empty($this->api_key)) {
            return $this->mockCreateRefund($payment_intent_id, $amount, $reason);
        }

        return null;
    }

    private function mockCreateRefund($payment_intent_id, $amount, $reason) {
        return [
            'id' => 're_mock_' . bin2hex(random_bytes(12)),
            'payment_intent' => $payment_intent_id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'succeeded',
            'created' => time()
        ];
    }

    public function webhookHandler($payload, $signature) {
        if (empty($this->api_key)) {
            return null;
        }

        return null;
    }

    private function makeRequest($endpoint, $method = 'POST', $data = []) {
        $url = $this->base_url . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return json_decode($response, true);
        }

        return null;
    }

    public function getPublishableKey() {
        return $this->publishable_key;
    }
}

?>
