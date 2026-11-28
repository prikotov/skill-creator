<?php
/**
 * Раннер тестов skill-creator: запускает тестовые файлы, сводит код выхода.
 * Один тестовый файл — на один тестируемый: scripts/hash.php → tests/hash.php.
 * Новый скрипт — новый tests/<имя>.php и строка в списке $tests.
 * Запуск: php tests/run.php. Код выхода 0/1.
 */
declare(strict_types=1);

$tests = ['docs/skill-md.php', 'docs/md-hygiene.php', 'scripts/create.php', 'scripts/hash.php'];
$fail = [];
foreach ($tests as $t) {
    $o = [];
    exec('php ' . escapeshellarg(__DIR__ . '/' . $t) . ' 2>&1', $o, $rc);
    echo ($rc === 0 ? 'OK  ' : 'FAIL ') . $t . "\n";
    if ($rc !== 0) {
        $fail[] = $t;
        echo implode("\n", $o), "\n";
    }
}
if ($fail !== []) {
    fwrite(STDERR, 'FAIL: ' . implode(', ', $fail) . "\n");
    exit(1);
}
