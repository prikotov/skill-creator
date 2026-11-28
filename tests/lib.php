<?php
/**
 * Общие помощники тестов skill-creator. Подключается из tests/*.php.
 */
declare(strict_types=1);

$GLOBALS['TEST_FAIL'] = 0;
$GLOBALS['TEST_N'] = 0;

function check(bool $cond, string $label): void
{
    $GLOBALS['TEST_N']++;
    if (!$cond) {
        $GLOBALS['TEST_FAIL']++;
        fwrite(STDERR, "FAIL: {$label}\n");
    }
}

function lintOk(string $file): bool
{
    $o = [];
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $o, $rc);
    return $rc === 0;
}

function runCreate(string $root, string $dir, array $args, &$rc): array
{
    $o = [];
    exec('php ' . escapeshellarg($root . '/scripts/create.php') . ' --dir ' . escapeshellarg($dir) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null', $o, $rc);
    return json_decode(implode("\n", $o), true) ?? [];
}

function testFinish(string $label): never
{
    $fail = $GLOBALS['TEST_FAIL'];
    $n = $GLOBALS['TEST_N'];
    if ($fail > 0) {
        fwrite(STDERR, "{$label}: {$fail} из {$n} тестов упали\n");
        exit(1);
    }
    echo "{$label}: OK, {$n} тестов\n";
    exit(0);
}
