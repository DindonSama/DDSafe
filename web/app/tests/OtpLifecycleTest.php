<?php

declare(strict_types=1);

use App\OTPManager;
use App\PocketBaseClient;

class FakePocketBaseClientForOtp extends PocketBaseClient
{
    public array $updates = [];
    public array $records = [];

    public function __construct()
    {
        parent::__construct('http://test.local');
        $this->records['otp_codes']['otp1'] = [
            'id' => 'otp1',
            'name' => 'GitHub',
            'secret_enc' => '',
            'is_personal' => false,
        ];
    }

    public function updateRecord(string $collection, string $id, array $data): array
    {
        $this->updates[] = [$collection, $id, $data];
        return $data;
    }

    public function getRecord(string $collection, string $id, array $params = []): ?array
    {
        return $this->records[$collection][$id] ?? null;
    }
}

return [
    [
        'name' => 'otp delete marks deleted',
        'test' => static function (): void {
            $pb = new FakePocketBaseClientForOtp();
            $otp = new OTPManager($pb, 'secret');

            $ok = $otp->delete('otp1', 'user1');
            assertTrue($ok, 'delete should succeed');
            $last = end($pb->updates);
            assertSame('otp_codes', $last[0], 'update should target otp_codes');
            assertSame('otp1', $last[1], 'updated id should match');
            assertTrue(!empty($last[2]['deleted']), 'deleted flag should be true');
        },
    ],
    [
        'name' => 'otp restore clears deleted fields',
        'test' => static function (): void {
            $pb = new FakePocketBaseClientForOtp();
            $otp = new OTPManager($pb, 'secret');

            $ok = $otp->restore('otp1');
            assertTrue($ok, 'restore should succeed');
            $last = end($pb->updates);
            assertSame(false, $last[2]['deleted'] ?? true, 'deleted should be false after restore');
            assertSame('', $last[2]['deleted_by'] ?? 'x', 'deleted_by should be cleared');
        },
    ],
];
