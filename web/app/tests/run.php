<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/*Test.php') ?: [];
$total = 0;
$failed = 0;

foreach ($testFiles as $file) {
    $cases = require $file;
    if (!is_array($cases)) {
        continue;
    }

    foreach ($cases as $case) {
        $total++;
        $name = (string)($case['name'] ?? basename($file));
        $fn = $case['test'] ?? null;

        try {
            if (!is_callable($fn)) {
                throw new RuntimeException('Test non exécutable');
            }
            $fn();
            echo "[OK] {$name}\n";
        } catch (Throwable $e) {
            $failed++;
            echo "[KO] {$name}: {$e->getMessage()}\n";
        }
    }
}

echo "\nRésultat: {$total} test(s), {$failed} échec(s).\n";
exit($failed > 0 ? 1 : 0);
