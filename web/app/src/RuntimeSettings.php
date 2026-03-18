<?php

declare(strict_types=1);

namespace App;

class RuntimeSettings
{
    private PocketBaseClient $pb;

    public function __construct(PocketBaseClient $pb)
    {
        $this->pb = $pb;
    }

    public function getJson(string $key, array $default = []): array
    {
        $record = $this->findByKey($key);
        if (!$record) {
            return $default;
        }

        $raw = (string)($record['value'] ?? '');
        if ($raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public function setJson(string $key, array $value): void
    {
        $payload = ['key' => $key, 'value' => json_encode($value, JSON_UNESCAPED_SLASHES) ?: '{}'];
        $record = $this->findByKey($key);

        if ($record) {
            $this->pb->updateRecord('app_runtime_settings', (string)$record['id'], $payload);
            return;
        }

        $this->pb->createRecord('app_runtime_settings', $payload);
    }

    private function findByKey(string $key): ?array
    {
        $safe = $this->escapeFilterValue($key);
        $result = $this->pb->listRecords('app_runtime_settings', [
            'filter' => 'key = "' . $safe . '"',
            'perPage' => 1,
            'page' => 1,
        ]);

        $items = $result['items'] ?? [];
        return !empty($items[0]) && is_array($items[0]) ? $items[0] : null;
    }

    private function escapeFilterValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
