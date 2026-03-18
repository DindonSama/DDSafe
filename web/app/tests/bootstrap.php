<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' (expected: ' . var_export($expected, true) . ', actual: ' . var_export($actual, true) . ')');
    }
}
