<?php
/**
 * Тесты create: тестируемый файл scripts/create.php — каркас нового навыка.
 * Отказы входа, структура каркаса, каркасные тесты, лицензии.
 * Запуск: php tests/create.php (или через tests/run.php). Код выхода 0/1.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/skill_creator_create_' . getmypid();
$dir = $tmp . '/demo-skill';

check(lintOk($root . '/scripts/create.php'), 'php -l scripts/create.php');

// 1. имя не kebab-case → отказ
$d = runCreate($root, $tmp . '/x', ['--name', 'Bad_Name'], $rc);
check($rc === 1 && ($d['ok'] ?? true) === false, 'create: имя не kebab-case — код 1');

// 1а. без --dir → отказ и ничего не создаётся (дефолта нет)
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/create.php') . ' --name no-dir 2>/dev/null', $o, $rc);
$d = json_decode(implode("\n", $o), true) ?? [];
check($rc === 1 && ($d['ok'] ?? true) === false, 'create: без --dir — код 1');
$leak = $root . '/no-dir';
check(!is_dir($leak), 'create: без --dir каталог не создан');
if (is_dir($leak)) {
    exec('rm -rf ' . escapeshellarg($leak));
}

// 1б. --dir недоступен → отказ, а не «ok: true» без файлов
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/create.php') . ' --name bad-dir --dir /dev/null/skill-creator-test 2>/dev/null', $o, $rc);
$d = json_decode(implode("\n", $o), true) ?? [];
check($rc === 1 && ($d['ok'] ?? true) === false && !isset($d['files']), 'create: --dir не создать — код 1, без файлов');
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/create.php') . ' --name ro-dir --dir /proc 2>/dev/null', $o, $rc);
$d = json_decode(implode("\n", $o), true) ?? [];
check($rc === 1 && ($d['ok'] ?? true) === false, 'create: --dir только для чтения — код 1');

// 2. каркас: структура и документация; скрипты и references агент добавляет сам
$d = runCreate($root, $dir, ['--name', 'demo-skill'], $rc);
check($rc === 0 && ($d['ok'] ?? false) === true, 'create: каркас создан');
foreach (['SKILL.md', 'README.md', 'AGENTS.md', 'CHANGELOG.md', 'LICENSE', '.github/workflows/ci.yml', 'tests/run.php', 'tests/lib.php', 'tests/docs/skill-md.php', '.gitignore'] as $f) {
    check(is_file($dir . '/' . $f), "create: есть {$f}");
}
check(!is_dir($dir . '/scripts') && !is_dir($dir . '/references'), 'create: scripts и references не создаются');
check(str_contains((string)file_get_contents($dir . '/CHANGELOG.md'), '## 0.1.0 — '), 'create: CHANGELOG с первой версией 0.1.0 и датой');
check(count($d['placeholders_left'] ?? []) > 0, 'create: плейсхолдеры перечислены');
check(!str_contains((string)file_get_contents($dir . '/SKILL.md'), '{{name}}'), 'create: name подставлен');
$readme = (string)file_get_contents($dir . '/README.md');
check(!str_contains($readme, 'scripts/'), 'create: README без упоминания scripts/');
foreach (['tests/run.php', 'tests/lib.php', 'tests/docs/skill-md.php'] as $tf) {
    check(lintOk($dir . '/' . $tf), "carcass: php -l {$tf}");
}

// 2а. каркасные тесты: честно падают на плейсхолдерах, из двух контекстов запуска
$o = [];
exec('php ' . escapeshellarg($dir . '/tests/run.php') . ' 2>&1', $o, $rc);
check($rc !== 0 && !str_contains(implode(' ', $o), 'frontmatter') && !str_contains(implode(' ', $o), 'раздел'), 'carcass: структура SKILL.md валидна');
check(str_contains(implode(' ', $o), 'плейсхолдеры'), 'carcass: тест требует заполнить плейсхолдеры');
$o2 = [];
exec('cd ' . escapeshellarg($dir) . ' && php tests/run.php 2>&1', $o2, $rc2);
check($rc2 !== 0 && str_contains(implode(' ', $o2), 'плейсхолдеры'), 'carcass: тот же результат из каталога навыка');

// 3. непустой каталог не трогается
$d = runCreate($root, $dir, ['--name', 'demo-skill'], $rc);
check($rc === 1, 'create: непустой каталог — код 1');

// 4. лицензия: none → без файла LICENSE и раздела; MIT → файл и frontmatter
$dirNoLic = $tmp . '/no-license';
$d = runCreate($root, $dirNoLic, ['--name', 'no-license', '--license', 'none'], $rc);
check($rc === 0 && !is_file($dirNoLic . '/LICENSE'), 'license none: без LICENSE');
$nl = (string)file_get_contents($dirNoLic . '/SKILL.md');
check(!str_contains($nl, 'license:'), 'license none: frontmatter без license');
check(!str_contains((string)file_get_contents($dirNoLic . '/README.md'), '## Лицензия'), 'license none: README без раздела');
check(str_contains((string)file_get_contents($dir . '/SKILL.md'), 'license: MIT'), 'license MIT: frontmatter');
check(str_contains((string)file_get_contents($dir . '/README.md'), '## Лицензия'), 'license MIT: раздел README');

// уборка
exec('rm -rf ' . escapeshellarg($tmp));

testFinish('create');
