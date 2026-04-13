<?php

declare(strict_types=1);

namespace App;

class AuditLogger
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
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                return $_SERVER['HTTP_X_REAL_IP'];
            }
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

    public function log(string $category, string $action, array $actor, array $context = []): void
    {
        try {
            $payload = [
                'category' => $category,
                'action' => $action,
                'actor' => (string)($actor['id'] ?? ''),
                'actor_name' => (string)($actor['name'] ?? $actor['email'] ?? ''),
                'target_id' => (string)($context['target_id'] ?? ''),
                'target_name' => (string)($context['target_name'] ?? ''),
                'tenant' => (string)($context['tenant'] ?? ''),
                'details' => json_encode($context['details'] ?? [], JSON_UNESCAPED_SLASHES),
                'ip' => self::getClientIp(),
                'logged_at' => date('c'),
            ];

            $this->pb->createRecord('audit_logs', $payload);
            $this->trimByAge();
            $this->trimOverflow();
        } catch (\Exception) {
            // Auditing must not break core features.
        }
    }

    public function latest(int $limit = 200): array
    {
        try {
            $result = $this->pb->listRecords('audit_logs', [
                'sort' => '-logged_at',
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
            $head = $this->pb->listRecords('audit_logs', [
                'sort' => '-logged_at',
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
                $oldest = $this->pb->listRecords('audit_logs', [
                    'sort' => 'logged_at',
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
                        $this->pb->deleteRecord('audit_logs', $id);
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
                $old = $this->pb->listRecords('audit_logs', [
                    'filter' => 'logged_at < "' . $threshold . '"',
                    'sort' => 'logged_at',
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
                        $this->pb->deleteRecord('audit_logs', $id);
                    }
                }
            }
        } catch (\Exception) {
            // Silent by design.
        }
    }
}
