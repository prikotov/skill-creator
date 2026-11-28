<?php
/**
 * Тесты hash: тестируемый файл scripts/hash.php — подсчёт и запись hash.
 * Печатает и вписывает hash каркаса; значение стабильно без изменений файлов.
 * Запуск: php tests/hash.php (или через tests/run.php). Код выхода 0/1.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/skill_creator_hash_' . getmypid();

check(lintOk($root . '/scripts/hash.php'), 'php -l scripts/hash.php');

// fixture: свежий каркас
$dir = $tmp . '/demo-skill';
runCreate($root, $dir, ['--name', 'demo-skill'], $rc);
check($rc === 0, 'hash: fixture-каркас создан');

// печать hash
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/hash.php') . ' ' . escapeshellarg($dir) . ' 2>/dev/null', $o, $rc);
$d = json_decode(implode("\n", $o), true) ?? [];
check($rc === 0 && ($d['ok'] ?? false) === true, 'hash: код 0, ok');
check(preg_match('~^[0-9a-f]{64}$~', (string)($d['hash'] ?? '')) === 1, 'hash: 64 hex');
$before = $d['hash'];

// --write: значение то же, в SKILL.md вписано
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/hash.php') . ' --write ' . escapeshellarg($dir) . ' 2>/dev/null', $o, $rc);
$d = json_decode(implode("\n", $o), true) ?? [];
check($rc === 0 && ($d['hash'] ?? '') === $before, 'hash --write: значение стабильно');
check(str_contains((string)file_get_contents($dir . '/SKILL.md'), 'hash: ' . $before), 'hash --write: вписан в SKILL.md');

// hash-строка в не-SKILL файле (как в CHANGELOG) не влияет на подсчёт
$chlog = $dir . '/CHANGELOG.md';
file_put_contents($chlog, "# Changelog\n\n## 0.1.0 — 2026-01-01\n\nhash: " . str_repeat('0', 64) . "\n");
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/hash.php') . ' ' . escapeshellarg($dir) . ' 2>/dev/null', $o, $rc);
$h1 = (json_decode(implode("\n", $o), true) ?? [])['hash'] ?? '';
check($h1 !== $before, 'hash: файл CHANGELOG вошёл в подсчёт');
file_put_contents($chlog, str_replace(str_repeat('0', 64), str_repeat('f', 64), (string)file_get_contents($chlog)));
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/hash.php') . ' ' . escapeshellarg($dir) . ' 2>/dev/null', $o, $rc);
$h2 = (json_decode(implode("\n", $o), true) ?? [])['hash'] ?? '';
check($h1 === $h2, 'hash: значение hash: в не-SKILL файле не влияет на подсчёт');

// --write меняет hash только во frontmatter: строки в теле не трогает
$sk = $dir . '/SKILL.md';
file_put_contents($sk, (string)file_get_contents($sk) . "\nhash: пример-в-теле\n");
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/hash.php') . ' --write ' . escapeshellarg($dir) . ' 2>/dev/null', $o, $rc);
check($rc === 0, 'hash --write: код 0 при строке hash в теле');
$skc = (string)file_get_contents($sk);
check(str_contains($skc, 'hash: пример-в-теле'), 'hash --write: строка в теле не заменена');
check(preg_match('~^hash: [0-9a-f]{64}$~m', $skc) === 1, 'hash --write: во frontmatter вписан hash');

// уборка
exec('rm -rf ' . escapeshellarg($tmp));

testFinish('hash');
