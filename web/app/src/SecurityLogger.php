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

    /**
     * Returns the real client IP, reading proxy headers when the direct
     * connection originates from a trusted private/Docker range.
     */
    private static function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $trustedRanges = ['10.', '172.', '192.168.', '127.', 'fc', 'fd'];
        $isTrustedProxy = false;
        foreach ($trustedRanges as $prefix) {
            if (str_starts_with($remoteAddr, $prefix)) {
                $isTrustedProxy = true;
                break;
            }
        }
        if ($isTrustedProxy) {
            // X-Real-IP is the most direct header set by Nginx Proxy Manager
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                return $_SERVER['HTTP_X_REAL_IP'];
            }
            // X-Forwarded-For may contain a chain; the first entry is the client
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $first = trim($parts[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return $remoteAddr;
    }

    public function logAuthFailure(string $identity, string $loginType, string $reason): void
    {
        try {
            $this->pb->createRecord('auth_failures', [
                'identity' => $identity,
                'login_type' => $loginType,
                'reason' => $reason,
                'ip' => self::getClientIp(),
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
