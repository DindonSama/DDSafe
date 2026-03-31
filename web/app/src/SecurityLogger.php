<?php

declare(strict_types=1);

namespace App;

class SecurityLogger
{
    private PocketBaseClient $pb;
    private int $maxEntries;
    private int $retentionDays;

    public function __construct(PocketBaseClient $pb, int $maxEntries = 500, int $retentionDays = 30)
    {
        $this->pb = $pb;
        $this->maxEntries = max(50, $maxEntries);
        $this->retentionDays = max(1, $retentionDays);
    }

    public function logAuthFailure(string $identity, string $loginType, string $reason): void
    {
        try {
            $this->pb->createRecord('auth_failures', [
                'identity' => $identity,
                'login_type' => $loginType,
                'reason' => $reason,
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'occurred_at' => date('c'),
            ]);
            $this->trimByAge();
            $this->trimOverflow();
        } catch (\Exception) {
            // Security logging must not block authentication flow.
        }
    }

    public function latestFailures(int $limit = 200): array
    {
        try {
            $result = $this->pb->listRecords('auth_failures', [
                'sort' => '-occurred_at',
                'perPage' => max(1, min($limit, 500)),
            ]);
            return $result['items'] ?? [];
        } catch (\Exception) {
            return [];
        }
    }

    private function trimOverflow(): void
    {
        try {
            $head = $this->pb->listRecords('auth_failures', [
                'sort' => '-occurred_at',
                'perPage' => 1,
                'page' => 1,
            ]);

            $total = (int)($head['totalItems'] ?? 0);
            if ($total <= $this->maxEntries) {
                return;
            }

            $overflow = $total - $this->maxEntries;
            while ($overflow > 0) {
                $batchSize = min($overflow, 100);
                $oldest = $this->pb->listRecords('auth_failures', [
                    'sort' => 'occurred_at',
                    'perPage' => $batchSize,
                    'page' => 1,
                ]);

                $items = $oldest['items'] ?? [];
                if (empty($items)) {
                    break;
                }

                foreach ($items as $item) {
                    $id = (string)($item['id'] ?? '');
                    if ($id !== '') {
                        $this->pb->deleteRecord('auth_failures', $id);
                        $overflow--;
                        if ($overflow <= 0) {
                            break;
                        }
                    }
                }
            }
        } catch (\Exception) {
            // Silent by design.
        }
    }

    private function trimByAge(): void
    {
        try {
            $threshold = gmdate('c', time() - ($this->retentionDays * 86400));

            while (true) {
                $old = $this->pb->listRecords('auth_failures', [
                    'filter' => 'occurred_at < "' . $threshold . '"',
                    'sort' => 'occurred_at',
                    'perPage' => 100,
                    'page' => 1,
                ]);

                $items = $old['items'] ?? [];
                if (empty($items)) {
                    break;
                }

                foreach ($items as $item) {
                    $id = (string)($item['id'] ?? '');
                    if ($id !== '') {
                        $this->pb->deleteRecord('auth_failures', $id);
                    }
                }
            }
        } catch (\Exception) {
            // Silent by design.
        }
    }
}
