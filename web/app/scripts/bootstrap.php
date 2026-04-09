<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$config = require __DIR__ . '/../config/config.php';
$pb = new \App\PocketBaseClient($config['pocketbase']['url']);
$setup = new \App\Setup($pb, $config);

$maxAttempts = max(1, (int)(getenv('SETUP_MAX_ATTEMPTS') ?: 20));
$delayMs = max(250, (int)(getenv('SETUP_RETRY_DELAY_MS') ?: 1500));

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        if (!$setup->isInitialized()) {
            $setup->initialize();
        }

        fwrite(STDOUT, "[bootstrap] PocketBase setup completed.\n");
        exit(0);
    } catch (\Throwable $e) {
        fwrite(
            STDERR,
            sprintf(
                "[bootstrap] Attempt %d/%d failed: %s\n",
                $attempt,
                $maxAttempts,
                $e->getMessage()
            )
        );

        if ($attempt < $maxAttempts) {
            usleep($delayMs * 1000);
        }
    }
}

fwrite(STDERR, "[bootstrap] Setup failed after all retry attempts.\n");
exit(1);
