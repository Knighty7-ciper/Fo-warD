<?php
// Email Configuration
// Configure your SMTP settings here

return [
    // Email sending method: 'smtp', 'sendmail', 'mail'
    'method' => getenv('EMAIL_METHOD') ?: 'smtp',
    
    // SMTP Configuration
    'smtp' => [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => getenv('SMTP_PORT') ?: 587,
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // 'tls' or 'ssl'
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'timeout' => 30,
    ],
    
    // From address
    'from' => [
        'address' => getenv('EMAIL_FROM_ADDRESS') ?: 'noreply@fowardlms.com',
        'name' => getenv('EMAIL_FROM_NAME') ?: 'FowarD LMS',
    ],
    
    // Reply-to address
    'reply_to' => [
        'address' => getenv('EMAIL_REPLY_TO') ?: 'support@fowardlms.com',
        'name' => getenv('EMAIL_REPLY_TO_NAME') ?: 'FowarD Support',
    ],
    
    // Queue settings
    'queue' => [
        'enabled' => true,
        'batch_size' => 50, // Number of emails to process per batch
        'retry_delay' => 300, // Seconds to wait before retrying failed emails
    ],
    
    // Rate limiting
    'rate_limit' => [
        'enabled' => true,
        'max_per_hour' => 100, // Maximum emails per hour
    ],
    
    // Testing mode (logs emails instead of sending)
    'testing_mode' => getenv('EMAIL_TESTING_MODE') === 'true',
    'test_recipient' => getenv('EMAIL_TEST_RECIPIENT') ?: '',
];
