<?php

declare(strict_types=1);

require_once '/var/www/app/vendor/autoload.php';

use App\BackupExporter;
use App\PocketBaseClient;
use App\RuntimeSettings;

function envBool(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function envInt(string $name, int $default): int
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return (int)$value;
}

function nowDateTime(): DateTimeImmutable
{
    return new DateTimeImmutable('now');
}

function normalizeSchedules(string $raw): array
{
    $allowed = ['daily', 'weekly', 'monthly'];
    return array_values(array_unique(array_filter(
        array_map('trim', explode(',', strtolower($raw))),
        static fn(string $s): bool => in_array($s, $allowed, true)
    )));
}

function normalizeRuntimeConfig(array $runtime, array $defaults): array
{
    $enabled = (bool)($runtime['enabled'] ?? $defaults['enabled']);
    $schedulesRaw = (string)($runtime['schedules'] ?? $defaults['schedules']);
    $schedules = normalizeSchedules($schedulesRaw);
    if (empty($schedules)) {
        $schedules = normalizeSchedules((string)$defaults['schedules']);
    }

    $exportMode = strtolower((string)($runtime['export_mode'] ?? $defaults['export_mode']));
    if (!in_array($exportMode, ['encrypted', 'plain'], true)) {
        $exportMode = 'encrypted';
    }

    return [
        'enabled' => $enabled,
        'schedules' => $schedules,
        'run_hour' => max(0, min(23, (int)($runtime['run_hour'] ?? $defaults['run_hour']))),
        'weekly_day' => max(1, min(7, (int)($runtime['weekly_day'] ?? $defaults['weekly_day']))),
        'monthly_day' => max(1, min(31, (int)($runtime['monthly_day'] ?? $defaults['monthly_day']))),
        'export_mode' => $exportMode,
        'include_secrets' => filter_var($runtime['include_secrets'] ?? $defaults['include_secrets'], FILTER_VALIDATE_BOOLEAN),
        'passphrase' => (string)($runtime['passphrase'] ?? $defaults['passphrase']),
        'retention_daily' => max(1, (int)($runtime['retention_daily'] ?? $defaults['retention_daily'])),
        'retention_weekly' => max(1, (int)($runtime['retention_weekly'] ?? $defaults['retention_weekly'])),
        'retention_monthly' => max(1, (int)($runtime['retention_monthly'] ?? $defaults['retention_monthly'])),
        'check_interval_seconds' => max(60, (int)($runtime['check_interval_seconds'] ?? $defaults['check_interval_seconds'])),
    ];
}

function periodKey(string $schedule, DateTimeImmutable $now): string
{
    return match ($schedule) {
        'daily' => $now->format('Y-m-d'),
        'weekly' => $now->format('o-W'),
        'monthly' => $now->format('Y-m'),
        default => $now->format('c'),
    };
}

function shouldRun(string $schedule, DateTimeImmutable $now, array $state, int $runHour, int $weeklyDay, int $monthlyDay): bool
{
    $hour = (int)$now->format('G');
    if ($hour < $runHour) {
        return false;
    }

    if ($schedule === 'weekly' && (int)$now->format('N') !== $weeklyDay) {
        return false;
    }

    if ($schedule === 'monthly') {
        $daysInMonth = (int)$now->format('t');
        $effectiveDay = min($monthlyDay, $daysInMonth);
        if ((int)$now->format('j') !== $effectiveDay) {
            return false;
        }
    }

    $currentKey = periodKey($schedule, $now);
    $lastKey = (string)($state[$schedule]['period_key'] ?? '');
    return $currentKey !== $lastKey;
}

function loadState(string $stateFile): array
{
    if (!is_file($stateFile)) {
        return [];
    }
    $raw = (string)@file_get_contents($stateFile);
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function saveState(string $stateFile, array $state): void
{
    @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function pruneScheduleFiles(string $outputDir, string $schedule, int $keep): void
{
    $keep = max(1, $keep);
    $patterns = [
        $outputDir . '/ddsafe-auto-' . $schedule . '-*.enc.json',
        $outputDir . '/ddsafe-auto-' . $schedule . '-*.json',
    ];

    $files = [];
    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                $files[$file] = filemtime($file) ?: 0;
            }
        }
    }

    arsort($files);
    $toDelete = array_slice(array_keys($files), $keep);
    foreach ($toDelete as $file) {
        @unlink($file);
    }
}

date_default_timezone_set((string)(getenv('TZ') ?: 'UTC'));

$defaults = [
    'enabled' => envBool('BACKUP_SCHEDULER_ENABLED', false),
    'schedules' => (string)(getenv('BACKUP_SCHEDULES') ?: 'daily,weekly,monthly'),
    'run_hour' => envInt('BACKUP_RUN_HOUR', 2),
    'weekly_day' => envInt('BACKUP_WEEKLY_DAY', 7),
    'monthly_day' => envInt('BACKUP_MONTHLY_DAY', 1),
    'export_mode' => (string)(getenv('BACKUP_EXPORT_MODE') ?: 'encrypted'),
    'include_secrets' => envBool('BACKUP_INCLUDE_SECRETS', false),
    'passphrase' => (string)(getenv('BACKUP_PASSPHRASE') ?: ''),
    'retention_daily' => envInt('BACKUP_RETENTION_DAILY', 14),
    'retention_weekly' => envInt('BACKUP_RETENTION_WEEKLY', 8),
    'retention_monthly' => envInt('BACKUP_RETENTION_MONTHLY', 12),
    'check_interval_seconds' => envInt('BACKUP_CHECK_INTERVAL_SECONDS', 300),
];

$outputDir = (string)(getenv('BACKUP_OUTPUT_DIR') ?: '/backups');
if ($outputDir === '') {
    $outputDir = '/backups';
}
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0775, true);
}
if (!is_dir($outputDir)) {
    fwrite(STDERR, "[backup-scheduler] output directory not writable: {$outputDir}\n");
    exit(1);
}

$config = require '/var/www/app/config/config.php';
$pb = new PocketBaseClient((string)($config['pocketbase']['url'] ?? 'http://pb_2fa:8090'));
$exporter = new BackupExporter($pb);
$runtimeSettings = new RuntimeSettings($pb);

$stateFile = $outputDir . '/.backup-scheduler-state.json';
$authReady = false;

fwrite(STDOUT, "[backup-scheduler] process started in php container\n");

while (true) {
    if (!$authReady) {
        try {
            $auth = $pb->authAdmin((string)$config['pocketbase']['admin_email'], (string)$config['pocketbase']['admin_password']);
            $pb->setToken((string)($auth['token'] ?? ''));
            $authReady = true;
            fwrite(STDOUT, "[backup-scheduler] authenticated to PocketBase\n");
        } catch (Throwable $e) {
            fwrite(STDERR, '[backup-scheduler] auth failed: ' . $e->getMessage() . "\n");
            sleep(max(60, (int)$defaults['check_interval_seconds']));
            continue;
        }
    }

    $runtime = [];
    try {
        $runtime = $runtimeSettings->getJson('backup_scheduler', []);
    } catch (Throwable $e) {
        fwrite(STDERR, '[backup-scheduler] runtime settings unavailable, using env defaults: ' . $e->getMessage() . "\n");
    }

    $active = normalizeRuntimeConfig(is_array($runtime) ? $runtime : [], $defaults);
    $checkInterval = max(60, (int)$active['check_interval_seconds']);

    if (!$active['enabled']) {
        sleep($checkInterval);
        continue;
    }

    if ($active['export_mode'] === 'encrypted' && strlen((string)$active['passphrase']) < 8) {
        fwrite(STDERR, "[backup-scheduler] passphrase invalide (<8) en mode encrypted; en attente d'une configuration valide.\n");
        sleep($checkInterval);
        continue;
    }

    $state = loadState($stateFile);
    $now = nowDateTime();

    foreach ((array)$active['schedules'] as $schedule) {
        if (!shouldRun(
            $schedule,
            $now,
            $state,
            (int)$active['run_hour'],
            (int)$active['weekly_day'],
            (int)$active['monthly_day']
        )) {
            continue;
        }

        try {
            $payload = $exporter->buildPayload($config, (bool)$active['include_secrets']);
            $suffix = !empty($active['include_secrets']) ? 'full' : 'metadata';
            $timestamp = $now->format('Ymd-His');

            if ($active['export_mode'] === 'encrypted') {
                $body = $exporter->encryptPayload($payload, (string)$active['passphrase']);
                $filePath = $outputDir . '/ddsafe-auto-' . $schedule . '-' . $timestamp . '-' . $suffix . '.enc.json';
            } else {
                $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($body === false) {
                    throw new RuntimeException('json encode failed');
                }
                $filePath = $outputDir . '/ddsafe-auto-' . $schedule . '-' . $timestamp . '-' . $suffix . '.json';
            }

            file_put_contents($filePath, $body);

            $state[$schedule] = [
                'period_key' => periodKey($schedule, $now),
                'last_run_at' => $now->format('c'),
                'last_file' => basename($filePath),
                'last_status' => 'success',
                'last_error' => '',
                'last_error_at' => '',
            ];
            saveState($stateFile, $state);

            $retentionBySchedule = [
                'daily' => (int)$active['retention_daily'],
                'weekly' => (int)$active['retention_weekly'],
                'monthly' => (int)$active['retention_monthly'],
            ];
            pruneScheduleFiles($outputDir, $schedule, $retentionBySchedule[$schedule] ?? 1);

            fwrite(STDOUT, '[backup-scheduler] backup generated: ' . basename($filePath) . "\n");
        } catch (Throwable $e) {
            $authReady = false;
            $state[$schedule] = [
                'period_key' => periodKey($schedule, $now),
                'last_run_at' => (string)($state[$schedule]['last_run_at'] ?? ''),
                'last_file' => (string)($state[$schedule]['last_file'] ?? ''),
                'last_status' => 'error',
                'last_error' => $e->getMessage(),
                'last_error_at' => $now->format('c'),
            ];
            saveState($stateFile, $state);
            fwrite(STDERR, '[backup-scheduler] error for ' . $schedule . ': ' . $e->getMessage() . "\n");
        }
    }

    sleep($checkInterval);
}
