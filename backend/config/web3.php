<?php

class Web3 {
    private static $blockchain_enabled = false;
    private static $network = 'mock';

    public static function generateCertificateHash($certificate_data) {
        $data_string = json_encode($certificate_data);
        return hash('sha256', $data_string . time());
    }

    public static function mintCertificateNFT($certificate_id, $student_id, $course_id, $metadata) {
        if (!self::$blockchain_enabled) {
            return self::mockMintNFT($certificate_id, $student_id, $course_id, $metadata);
        }

        return null;
    }

    private static function mockMintNFT($certificate_id, $student_id, $course_id, $metadata) {
        $nft_data = [
            'certificate_id' => $certificate_id,
            'student_id' => $student_id,
            'course_id' => $course_id,
            'metadata' => $metadata,
            'timestamp' => time(),
            'network' => self::$network
        ];

        $blockchain_hash = self::generateCertificateHash($nft_data);

        $mock_transaction = [
            'tx_hash' => $blockchain_hash,
            'block_number' => rand(1000000, 9999999),
            'network' => self::$network,
            'status' => 'confirmed',
            'gas_used' => '0',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $mock_transaction;
    }

    public static function verifyCertificate($blockchain_hash, $certificate_data) {
        if (!self::$blockchain_enabled) {
            return self::mockVerifyCertificate($blockchain_hash, $certificate_data);
        }

        return false;
    }

    private static function mockVerifyCertificate($blockchain_hash, $certificate_data) {
        if (empty($blockchain_hash)) {
            return false;
        }

        return [
            'valid' => true,
            'verified_at' => date('Y-m-d H:i:s'),
            'network' => self::$network,
            'blockchain_hash' => $blockchain_hash
        ];
    }

    public static function getCertificateMetadata($certificate_id) {
        try {
            $db = Database::getInstance();

            $sql = "SELECT c.*, u.first_name, u.last_name, u.email, co.title as course_title
                    FROM certificates c
                    JOIN users u ON c.student_id = u.id
                    JOIN courses co ON c.course_id = co.id
                    WHERE c.id = :certificate_id";

            $cert = $db->selectOne($sql, [':certificate_id' => $certificate_id]);

            if (!$cert) {
                return null;
            }

            return [
                'name' => 'Forward LMS Certificate',
                'description' => "Certificate of completion for {$cert['course_title']}",
                'image' => '/frontend/assets/images/certificate-template.png',
                'attributes' => [
                    [
                        'trait_type' => 'Student Name',
                        'value' => $cert['first_name'] . ' ' . $cert['last_name']
                    ],
                    [
                        'trait_type' => 'Course',
                        'value' => $cert['course_title']
                    ],
                    [
                        'trait_type' => 'Certificate Number',
                        'value' => $cert['certificate_number']
                    ],
                    [
                        'trait_type' => 'Issue Date',
                        'value' => date('Y-m-d', strtotime($cert['issued_at']))
                    ],
                    [
                        'trait_type' => 'Blockchain Hash',
                        'value' => $cert['blockchain_hash']
                    ]
                ]
            ];
        } catch (Exception $e) {
            error_log("Get certificate metadata failed: " . $e->getMessage());
            return null;
        }
    }

    public static function getBlockchainExplorerUrl($tx_hash) {
        if (self::$network === 'mock') {
            return "/frontend/verify-certificate.php?hash={$tx_hash}";
        }

        return null;
    }

    public static function isBlockchainEnabled() {
        return self::$blockchain_enabled;
    }

    public static function setBlockchainEnabled($enabled) {
        self::$blockchain_enabled = $enabled;
    }
}

?>
