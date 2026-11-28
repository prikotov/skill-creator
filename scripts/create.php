<?php
/**
 * skill-creator: каркас нового навыка.
 *
 *   php scripts/create.php --name my-skill --dir /path/to/my-skill [--title ".."] [--description ".."]
 *       [--owner prikotov] [--license MIT|none|SPDX-ID]
 *
 * Создаёт структуру и документацию навыка: SKILL.md, README, AGENTS.md,
 * CHANGELOG.md, тесты, CI. Скрипты и references/ агент добавляет сам по мере
 * надобности. Существующий непустой каталог не трогает (код выхода 1).
 * git init/commit не делает.
 * stdout — JSON: файлы, оставшиеся плейсхолдеры, следующие шаги.
 */
declare(strict_types=1);

const ROOT = __DIR__ . '/..';

/** Разбор аргументов: --key value (повторяемые --key собираются в массив). */
function argv(array $argv): array
{
    $opt = [];
    $n = count($argv);
    for ($i = 1; $i < $n; $i++) {
        if (str_starts_with($argv[$i], '--') && isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '--')) {
            $k = substr($argv[$i], 2);
            if (isset($opt[$k])) {
                $opt[$k] = is_array($opt[$k]) ? $opt[$k] : [$opt[$k]];
                $opt[$k][] = $argv[++$i];
            } else {
                $opt[$k] = $argv[++$i];
            }
        }
    }
    return $opt;
}

function fail(string $msg): never
{
    fwrite(STDERR, $msg . "\n");
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE), "\n";
    exit(1);
}

/** Рендер шаблона с подстановкой плейсхолдеров. */
function render(string $tpl, array $map): string
{
    $t = file_get_contents(ROOT . '/assets/' . $tpl);
    $t = preg_replace("~\n{3,}~", "\n\n", $t);
    $t = strtr($t, $map);
    // без лицензии: строка frontmatter и раздел README исчезают
    if (($map['{{license}}'] ?? '') === 'none') {
        $t = preg_replace('~^license: none\n~m', '', $t);
        $t = preg_replace('~^## Лицензия\n\nnone\n?~m', '', $t);
    }
    return $t;
}

$opt = argv($argv);
$name = $opt['name'] ?? '';
$dir = $opt['dir'] ?? '';
if (!preg_match('~^[a-z0-9]+(-[a-z0-9]+)*$~', $name)) {
    fail('usage: php scripts/create.php --name kebab-case-name --dir /path/to/repo [--title ..] [--description ..]');
}
if (strlen($name) > 64) {
    fail('имя навыка длиннее 64 символов — лимит Agent Skills');
}
// --dir обязателен: дефолта нет, каркас не раскладывается «куда попало»
if ($dir === '') {
    fail("--dir обязателен: каталог нового репо, например --dir /path/to/{$name}");
}

$title = $opt['title'] ?? ucwords(str_replace('-', ' ', $name));
$description = $opt['description'] ?? 'Что делает skill (кратко)';
$owner = $opt['owner'] ?? 'prikotov';
$license = $opt['license'] ?? 'MIT';

// все аргументы проверены — цель должна создаться и быть доступна на запись,
// иначе отказ до генерации файлов (не «ok: true» без файлов)
if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
    fail("каталог не создать: {$dir}");
}
if (!is_writable($dir)) {
    fail("каталог не доступен на запись: {$dir}");
}
if (count(scandir($dir)) > 2) {
    fail("каталог не пуст: {$dir}");
}

$map = [
    '{{name}}' => $name,
    '{{Title}}' => $title,
    '{{description}}' => $description,
    '{{owner}}' => $owner,
    '{{license}}' => $license,
    '{{updated}}' => gmdate('Y-m-d H:i') . ' UTC',
];

$created = [];
$put = function (string $rel, string $text) use ($dir, &$created): void {
    $out = $dir . '/' . $rel;
    if (!is_dir(dirname($out)) && !@mkdir(dirname($out), 0777, true)) {
        fail("каталог не создать: " . dirname($out));
    }
    if (file_put_contents($out, $text) === false) {
        fail("файл не записан: {$out}");
    }
    $created[] = $rel;
};

// Документы
$put('SKILL.md', render('SKILL.template.md', $map));
$put('README.md', render('README.template.md', $map));
$put('AGENTS.md', render('AGENTS.template.md', $map));
$put('.github/workflows/ci.yml', render('ci.template.yml', $map));
if ($license === 'MIT') {
    $put('LICENSE', (string)file_get_contents(ROOT . '/LICENSE'));
} elseif ($license !== 'none') {
    $put('LICENSE', "License: {$license}\n\nТекст лицензии добавьте вручную.\n");
}
$put('CHANGELOG.md', "# Изменения\n\n## 0.1.0 — " . gmdate('Y-m-d H:i') . " UTC\n\n- Первый выпуск.\n");
$put('.gitignore', "# Cache & downloads\ndata/\n\n# IDE\n.vscode/\n.idea/\n*.swp\n*~\n\n# OS\n.DS_Store\nThumbs.db\n");

// Тесты каркаса: helpers, структура SKILL.md, раннер. Честно падают на плейсхолдерах
$put('tests/lib.php', <<<PHP
<?php
/**
 * Общие помощники тестов {$name}. Подключается из tests/*.php.
 */
declare(strict_types=1);

\$GLOBALS['TEST_FAIL'] = 0;
\$GLOBALS['TEST_N'] = 0;

function check(bool \$cond, string \$label): void
{
    \$GLOBALS['TEST_N']++;
    if (!\$cond) {
        \$GLOBALS['TEST_FAIL']++;
        fwrite(STDERR, "FAIL: {\$label}\\n");
    }
}

function testFinish(string \$label): never
{
    \$fail = \$GLOBALS['TEST_FAIL'];
    \$n = \$GLOBALS['TEST_N'];
    if (\$fail > 0) {
        fwrite(STDERR, "{\$label}: {\$fail} из {\$n} тестов упали\\n");
        exit(1);
    }
    echo "{\$label}: OK, {\$n} тестов\\n";
    exit(0);
}

PHP);

$put('tests/docs/skill-md.php', <<<PHP
<?php
/**
 * Офлайн-тесты {$name}: структура SKILL.md. Сеть не трогают.
 * Запуск: php tests/docs/skill-md.php. Код выхода 0/1.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

// SKILL.md: frontmatter и обязательные разделы
\$skill = (string)file_get_contents(dirname(__DIR__, 2) . '/SKILL.md');
\$lines = explode("\n", \$skill);
check((\$lines[0] ?? '') === '---', 'SKILL.md: frontmatter открывается');
check(str_starts_with(\$lines[1] ?? '', 'name: {$name}'), 'SKILL.md: name');
check(str_starts_with(\$lines[2] ?? '', 'description: '), 'SKILL.md: description');
check(array_search('---', array_slice(\$lines, 1, 20), true) !== false, 'SKILL.md: frontmatter закрывается');
foreach (['Когда использовать', 'Как использовать', 'Результат', 'Правила для агента'] as \$h) {
    check(str_contains(\$skill, '## ' . \$h), "SKILL.md: раздел {\$h}");
}
check(substr_count(\$skill, "\n") <= 500, 'SKILL.md: до 500 строк — рекомендация Agent Skills');
check((bool)preg_match('/^hash: [0-9a-f]{64}\$/m', \$skill), 'SKILL.md: hash заполнен');
check(!str_contains(\$skill, '{{'), 'SKILL.md: плейсхолдеры заполнены');

testFinish('docs');

PHP);

$put('tests/run.php', <<<PHP
<?php
/**
 * Раннер тестов {$name}: запускает тестовые файлы, сводит код выхода.
 * Один тестовый файл — на один тестируемый: scripts/xxx.php → tests/xxx.php.
 * Новый скрипт — новый tests/xxx.php и строка в списке \$tests.
 * Запуск: php tests/run.php. Код выхода 0/1.
 */
declare(strict_types=1);

\$tests = ['docs/skill-md.php'];
\$fail = [];
foreach (\$tests as \$t) {
    \$o = [];
    exec('php ' . escapeshellarg(__DIR__ . '/' . \$t) . ' 2>&1', \$o, \$rc);
    echo (\$rc === 0 ? 'OK  ' : 'FAIL ') . \$t . "\n";
    if (\$rc !== 0) {
        \$fail[] = \$t;
        echo implode("\n", \$o), "\n";
    }
}
if (\$fail !== []) {
    exit(1);
}

PHP);
// hash каркаса считает scripts/hash.php — единственная реализация процедуры
// из references/package.md
exec('php ' . escapeshellarg(ROOT . '/scripts/hash.php') . ' --write ' . escapeshellarg($dir), $hashOut, $hashRc);
if ($hashRc !== 0) {
    fail('hash каркаса не посчитан: ' . implode('; ', $hashOut));
}

// оставшиеся плейсхолдеры
$left = [];
foreach ($created as $rel) {
    if (!str_ends_with($rel, '.md')) {
        continue;
    }
    preg_match_all('~\{\{[^#/}][^}]*\}\}~', (string)file_get_contents($dir . '/' . $rel), $m);
    foreach ($m[0] as $ph) {
        $left[$ph][] = $rel;
    }
}

echo json_encode([
    'ok' => true,
    'files' => $created,
    'placeholders_left' => $left,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
