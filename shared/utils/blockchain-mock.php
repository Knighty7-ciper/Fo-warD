<?php

class BlockchainMock {
    private static $blocks = [];
    private static $difficulty = 4;

    public static function createGenesisBlock() {
        $genesis = [
            'index' => 0,
            'timestamp' => time(),
            'data' => 'Genesis Block',
            'previous_hash' => '0',
            'nonce' => 0
        ];
        $genesis['hash'] = self::calculateHash($genesis);
        self::$blocks[] = $genesis;
        return $genesis;
    }

    public static function addBlock($data) {
        if (empty(self::$blocks)) {
            self::createGenesisBlock();
        }

        $previous_block = end(self::$blocks);
        $new_block = [
            'index' => $previous_block['index'] + 1,
            'timestamp' => time(),
            'data' => $data,
            'previous_hash' => $previous_block['hash'],
            'nonce' => 0
        ];

        $new_block = self::mineBlock($new_block);
        self::$blocks[] = $new_block;

        return $new_block;
    }

    private static function mineBlock($block) {
        $target = str_repeat('0', self::$difficulty);

        while (substr($block['hash'] ?? '', 0, self::$difficulty) !== $target) {
            $block['nonce']++;
            $block['hash'] = self::calculateHash($block);
        }

        return $block;
    }

    private static function calculateHash($block) {
        $data = $block['index'] .
                $block['timestamp'] .
                json_encode($block['data']) .
                $block['previous_hash'] .
                $block['nonce'];

        return hash('sha256', $data);
    }

    public static function validateChain() {
        for ($i = 1; $i < count(self::$blocks); $i++) {
            $current_block = self::$blocks[$i];
            $previous_block = self::$blocks[$i - 1];

            if ($current_block['hash'] !== self::calculateHash($current_block)) {
                return false;
            }

            if ($current_block['previous_hash'] !== $previous_block['hash']) {
                return false;
            }
        }

        return true;
    }

    public static function getBlock($hash) {
        foreach (self::$blocks as $block) {
            if ($block['hash'] === $hash) {
                return $block;
            }
        }
        return null;
    }

    public static function getChain() {
        return self::$blocks;
    }

    public static function getLatestBlock() {
        return end(self::$blocks);
    }

    public static function mintCertificateNFT($certificate_data) {
        $nft_data = [
            'type' => 'certificate',
            'certificate_id' => $certificate_data['id'],
            'student_id' => $certificate_data['student_id'],
            'course_id' => $certificate_data['course_id'],
            'certificate_number' => $certificate_data['certificate_number'],
            'issued_at' => $certificate_data['issued_at'],
            'metadata' => [
                'student_name' => $certificate_data['student_name'],
                'course_title' => $certificate_data['course_title']
            ]
        ];

        $block = self::addBlock($nft_data);

        return [
            'success' => true,
            'blockchain_hash' => $block['hash'],
            'block_index' => $block['index'],
            'timestamp' => $block['timestamp'],
            'nonce' => $block['nonce']
        ];
    }

    public static function verifyCertificate($blockchain_hash) {
        $block = self::getBlock($blockchain_hash);

        if (!$block) {
            return [
                'valid' => false,
                'message' => 'Certificate not found on blockchain'
            ];
        }

        $is_valid = self::validateChain();

        return [
            'valid' => $is_valid,
            'block' => $block,
            'verified_at' => date('Y-m-d H:i:s'),
            'message' => $is_valid ? 'Certificate is valid' : 'Blockchain integrity compromised'
        ];
    }

    public static function getBlockchainStats() {
        return [
            'total_blocks' => count(self::$blocks),
            'latest_block' => self::getLatestBlock(),
            'difficulty' => self::$difficulty,
            'is_valid' => self::validateChain()
        ];
    }
}

?>
