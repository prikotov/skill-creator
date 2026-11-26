<?php
/**
 * Офлайн-тесты skill-creator: каркас во временном каталоге должен
 * создаваться, проходить php -l и свои тесты; сеть не трогают.
 * Запуск: php tests/run.php. Код выхода 0/1.
 */
declare(strict_types=1);

$fail = 0;
$n = 0;
function check(bool $cond, string $label): void
{
    global $fail, $n;
    $n++;
    if (!$cond) {
        $fail++;
        fwrite(STDERR, "FAIL: {$label}\n");
    }
}

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/skill_creator_test_' . getmypid();
$dir = $tmp . '/demo-skill';
$dirNo = $tmp . '/no-scripts';
$dirMany = $tmp . '/many-scripts';

function runNew(string $root, string $dir, array $args, &$rc): array
{
    $o = [];
    exec('php ' . escapeshellarg($root . '/scripts/new.php') . ' --dir ' . escapeshellarg($dir) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null', $o, $rc);
    return json_decode(implode("\n", $o), true) ?? [];
}
function lintOk(string $file): bool
{
    $o = [];
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $o, $rc);
    return $rc === 0;
}

// 1. имя не kebab-case → отказ
$d = runNew($root, $tmp . '/x', ['--name', 'Bad_Name'], $rc);
check($rc === 1 && ($d['ok'] ?? true) === false, 'new: имя не kebab-case — код 1');

// 2. один скрипт
$d = runNew($root, $dir, ['--name', 'demo-skill', '--script', 'main'], $rc);
check($rc === 0 && ($d['ok'] ?? false) === true, 'new: каркас создан');
foreach (['SKILL.md', 'README.md', 'AGENTS.md', 'LICENSE', '.github/workflows/ci.yml', 'references/api.md', 'scripts/main.php', 'DemoSkillClient.php', 'tests/run.php', '.gitignore'] as $f) {
    check(is_file($dir . '/' . $f), "new: есть {$f}");
}
check(count($d['placeholders_left'] ?? []) > 0, 'new: плейсхолдеры перечислены');
$readme = (string)file_get_contents($dir . '/README.md');
check(str_contains($readme, 'skill для AI-агентов'), 'new: README говорит, что это skill');
check(!str_contains((string)file_get_contents($dir . '/SKILL.md'), '{{name}}'), 'new: name подставлен');
check(!str_contains($readme, 'scripts/main.php'), 'new: README без ручного запуска скриптов (это в SKILL.md)');
check(str_contains((string)file_get_contents($dir . '/SKILL.md'), 'scripts/main.php'), 'new: блок скрипта раскрыт в SKILL.md');
foreach (['DemoSkillClient.php', 'scripts/main.php', 'tests/run.php'] as $f) {
    check(lintOk($dir . '/' . $f), "carcass: php -l {$f}");
}
$o = [];
exec('php ' . escapeshellarg($dir . '/tests/run.php') . ' 2>&1', $o, $rc);
// каркасные тесты падают ровно на плейсхолдерах (их заполняет агент), не на структуре
check($rc !== 0 && !str_contains(implode(' ', $o), 'frontmatter') && !str_contains(implode(' ', $o), 'раздел'), 'carcass: структура SKILL.md валидна');
check(str_contains(implode(' ', $o), 'плейсхолдеры'), 'carcass: тест требует заполнить плейсхолдеры');
$o = [];
exec('php ' . escapeshellarg($dir . '/scripts/main.php') . ' 2>&1', $o, $rc);
$m = json_decode(implode("\n", $o), true);
check($rc === 0 && ($m['ok'] ?? false) === true, 'carcass: скрипт отдаёт JSON');

// 3. непустой каталог не трогается
$d = runNew($root, $dir, ['--name', 'demo-skill'], $rc);
check($rc === 1, 'new: непустой каталог — код 1');

// 4. без скриптов: только инструкция, без клиента и scripts/
$d = runNew($root, $dirNo, ['--name', 'no-scripts'], $rc);
check($rc === 0 && ($d['ok'] ?? false) === true, 'no-scripts: каркас создан');
check(!is_dir($dirNo . '/scripts') && !is_file($dirNo . '/NoScriptsClient.php'), 'no-scripts: без scripts/ и клиента');
check(is_file($dirNo . '/tests/run.php') && is_file($dirNo . '/SKILL.md'), 'no-scripts: SKILL.md и тесты есть');
check(!str_contains((string)file_get_contents($dirNo . '/README.md'), 'scripts/'), 'no-scripts: README без упоминания scripts/');
$o = [];
exec('php ' . escapeshellarg($dirNo . '/tests/run.php') . ' 2>&1', $o, $rc);
check($rc !== 0 && str_contains(implode(' ', $o), 'плейсхолдеры'), 'no-scripts: тесты честно падают на плейсхолдерах');

// 5. несколько скриптов
$d = runNew($root, $dirMany, ['--name', 'many-scripts', '--script', 'map', '--script', 'search'], $rc);
check($rc === 0 && count($d['scripts'] ?? []) === 2, 'many: два скрипта');
check(is_file($dirMany . '/scripts/map.php') && is_file($dirMany . '/scripts/search.php'), 'many: оба файла');
$ci = (string)file_get_contents($dirMany . '/.github/workflows/ci.yml');
check(substr_count($ci, 'php -l scripts/') === 2, 'many: CI проверяет оба скрипта');
$sk = (string)file_get_contents($dirMany . '/SKILL.md');
check(substr_count($sk, '**Шаг N:') === 2, 'many: SKILL.md — по шагу на скрипт');
check(lintOk($dirMany . '/scripts/search.php'), 'many: php -l search.php');

// 6. --script не kebab-case → отказ
$d = runNew($root, $tmp . '/y', ['--name', 'ok-name', '--script', 'Bad'], $rc);
check($rc === 1, 'new: --script не kebab-case — код 1');

// 5а. лицензия: none → без файла LICENSE, без раздела; MIT → файл и frontmatter
$dirNoLic = $tmp . '/no-license';
$d = runNew($root, $dirNoLic, ['--name', 'no-license', '--license', 'none'], $rc);
check($rc === 0 && !is_file($dirNoLic . '/LICENSE'), 'license none: без LICENSE');
$nl = (string)file_get_contents($dirNoLic . '/SKILL.md');
check(!str_contains($nl, 'license:'), 'license none: frontmatter без license');
check(!str_contains((string)file_get_contents($dirNoLic . '/README.md'), '## Лицензия'), 'license none: README без раздела');
check(str_contains((string)file_get_contents($dir . '/SKILL.md'), 'license: MIT'), 'license MIT: frontmatter');
check(str_contains((string)file_get_contents($dir . '/README.md'), '## Лицензия'), 'license MIT: раздел README');

// 6. markdown-гигиена своих документов: маркер списка только с начала строки
foreach (['SKILL.md', 'README.md', 'references/rules.md', 'AGENTS.md'] as $doc) {
    $lines = file($root . '/' . $doc, FILE_IGNORE_NEW_LINES);
    $bad = 0;
    foreach ($lines as $ln) {
        // строка списка начинается с "- " (с отступом ≤2) — иначе это склейка
        if (preg_match('~^\s*- [A-ZА-ЯЁ]~u', $ln) === 0 && preg_match('~[.)»]\s+- [A-ZА-ЯЁ]~u', $ln) === 1) {
            $bad++;
        }
    }
    check($bad === 0, "md: склеенный маркер списка в {$doc} ({$bad})");
}

// уборка
exec('rm -rf ' . escapeshellarg($tmp));

if ($fail > 0) {
    fwrite(STDERR, "{$fail} из {$n} тестов упали\n");
    exit(1);
}
echo "OK: {$n} тестов\n";
