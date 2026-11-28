<?php
/**
 * Тесты docs/skill-md: тестируемый файл SKILL.md.
 * Конвенции: размер до 500 строк, имя до 64 и = имени папки.
 * Запуск: php tests/docs/skill-md.php (или через tests/run.php). Код выхода 0/1.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

$root = dirname(__DIR__, 2);

// размер SKILL.md — рекомендация Agent Skills: до 500 строк
$lines = count(file($root . '/SKILL.md', FILE_IGNORE_NEW_LINES));
check($lines <= 500, "skill-md: SKILL.md до 500 строк ({$lines})");

// name: до 64 символов (лимит Agent Skills); имя папки = name
$skill = (string)file_get_contents($root . '/SKILL.md');
preg_match('~^name: ([a-z0-9-]+)~m', $skill, $m);
$name = $m[1] ?? '';
check($name !== '' && strlen($name) <= 64, "skill-md: name до 64 символов ({$name})");
check($name !== '' && $name === basename($root), 'skill-md: имя папки = name (' . basename($root) . ')');

testFinish('skill-md');
