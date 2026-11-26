<?php
/**
 * skill-creator: каркас нового скилла.
 *
 *   php scripts/create.php --name my-skill --dir /path/to/my-skill [--title ".."] [--description ".."]
 *       [--owner prikotov] [--license MIT|none|SPDX-ID] [--script name1] [--script name2]...
 *
 * Скрипты не обязательны: скилл может быть только инструкцией. Каждый
 * --script добавляет scripts/<name>.php с заготовкой; при наличии хотя бы
 * одного скрипта создаётся общий клиент <Name>Client.php и tests/run.php
 * (проверяет клиент и структуру SKILL.md). Существующий непустой каталог
 * не трогает (код выхода 1). git init/commit не делает.
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

/** Рендер шаблона с подстановкой; блоки {{#scripts}}…{{/scripts}} повторяются по скриптам. */
function render(string $tpl, array $map, array $scripts): string
{
    $t = file_get_contents(ROOT . '/assets/' . $tpl);
    // повторяемый блок: {{#scripts}}…{{/scripts}} — по одному разу на скрипт;
    // без скриптов блок исчезает целиком (вместе с строкой, где стоит маркер)
    while (preg_match('~\{\{#scripts\}\}(.*?)\{\{/scripts\}\}~s', $t, $m)) {
        $rep = '';
        foreach ($scripts as $sc) {
            $rep .= strtr($m[1], ['{{script}}' => $sc['name'], '{{what}}' => $sc['what']]);
        }
        if ($rep === '' && str_contains($m[1], "|\n")) {
            $rep = '';
        }
        $t = str_replace($m[0], $rep, $t);
    }
    // строка таблицы-заглушки без скриптов → убрать пустые строки-остатки
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
    fail('usage: php scripts/create.php --name kebab-case-name --dir /path/to/repo [--title ..] [--description ..] [--script name]...');
}
// --dir обязателен: дефолта нет, каркас не раскладывается «куда попало»
if ($dir === '') {
    fail("--dir обязателен: каталог нового репо, например --dir /path/to/{$name}");
}

$title = $opt['title'] ?? ucwords(str_replace('-', ' ', $name));
$description = $opt['description'] ?? 'Что делает skill (кратко)';
$owner = $opt['owner'] ?? 'prikotov';
$license = $opt['license'] ?? 'MIT';
$rawScripts = isset($opt['script']) ? (array)$opt['script'] : [];
$scripts = [];
foreach ($rawScripts as $rs) {
    if (!preg_match('~^[a-z0-9]+(-[a-z0-9]+)*$~', $rs)) {
        fail("--script: kebab-case, получено {$rs}");
    }
    $scripts[] = ['name' => $rs, 'what' => 'что делает ' . $rs];
}
$client = implode('', array_map(fn($p) => ucfirst($p), explode('-', $name))) . 'Client';

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
    '{{Client}}' => $client,
    '{{owner}}' => $owner,
    '{{license}}' => $license,
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
$put('SKILL.md', render('SKILL.template.md', $map, $scripts));
$put('README.md', render('README.template.md', $map, $scripts));
$put('AGENTS.md', render('AGENTS.template.md', $map, $scripts));
$put('.github/workflows/ci.yml', render('ci.template.yml', $map, $scripts));
if ($license === 'MIT') {
    $put('LICENSE', (string)file_get_contents(ROOT . '/LICENSE'));
} elseif ($license !== 'none') {
    $put('LICENSE', "License: {$license}\n\nТекст лицензии добавьте вручную.\n");
}
$put('references/api.md', "# Механика: {$title} (проверено " . date('Y-m-d') . ")\n\nТолько проверенные факты с датой: поведение сайтов/API, ловушки,\nотрицательные результаты. Сюда — не инструкция для агента (это SKILL.md).\n");
$put('.gitignore', "# Cache & downloads\ndata/\n\n# IDE\n.vscode/\n.idea/\n*.swp\n*~\n\n# OS\n.DS_Store\nThumbs.db\n");

// Тесты: всегда; с клиентом — плюс его проверки
$testBody = '';
$requires = '';
if ($scripts !== []) {
    $put("{$client}.php", <<<PHP
<?php
/**
 * {$title} — общий клиент скриптов.
 *
 * PHP 8.4+, ext-curl. Без composer.
 */
declare(strict_types=1);

final class {$client}
{
    public const UA = 'Mozilla/5.0 (research; {$name})';

    /** GET через curl. @return array{body:string,status:int} */
    public static function fetch(string \$url, int \$timeout = 30): array
    {
        \$ch = curl_init(\$url);
        curl_setopt_array(\$ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => \$timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => self::UA,
        ]);
        \$body = curl_exec(\$ch);
        \$status = (int)curl_getinfo(\$ch, CURLINFO_RESPONSE_CODE);
        \$err = curl_error(\$ch);
        curl_close(\$ch);
        return ['body' => is_string(\$body) ? \$body : \$err, 'status' => \$status];
    }

    /** Разбор аргументов: --key value + флаги. */
    public static function argv(array \$argv, array \$flags = []): array
    {
        \$opt = [];
        \$pos = [];
        \$n = count(\$argv);
        for (\$i = 1; \$i < \$n; \$i++) {
            \$a = \$argv[\$i];
            if (str_starts_with(\$a, '--')) {
                \$k = substr(\$a, 2);
                if (in_array(\$k, \$flags, true)) {
                    \$opt[\$k] = true;
                } else {
                    \$opt[\$k] = \$argv[\$i + 1] ?? '';
                    \$i++;
                }
            } else {
                \$pos[] = \$a;
            }
        }
        return [\$pos, \$opt];
    }
}

PHP);
    $requires = "require dirname(__DIR__) . '/{$client}.php';\n";
    $testBody = <<<PHP

// клиент
[\$pos, \$opt] = {$client}::argv(['x', '--query', 'тест', '--flag'], ['flag']);
check(\$opt['query'] === 'тест', 'argv: значение');
check(\$opt['flag'] === true, 'argv: флаг');
check(\$pos === [], 'argv: позиционных нет');

PHP;
    foreach ($scripts as $sc) {
        $put("scripts/{$sc['name']}.php", <<<PHP
<?php
/**
 * {$title}, скрипт {$sc['name']}: {$sc['what']}.
 *
 *   php scripts/{$sc['name']}.php [--opt value]
 *
 * stdout — JSON; прогресс — в stderr. Код выхода 0/1.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/{$client}.php';

\$opt = {$client}::argv(\$argv);
\$result = ['ok' => true, 'items' => []];
echo json_encode(\$result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\\n";

PHP);
    }
}

$put('tests/run.php', <<<PHP
<?php
/**
 * Офлайн-тесты {$name}: структура SKILL.md и чистые функции; сеть не трогают.
 * Запуск: php tests/run.php. Код выхода 0/1.
 */
declare(strict_types=1);

{$requires}\$fail = 0;
\$n = 0;
function check(bool \$cond, string \$label): void
{
    global \$fail, \$n;
    \$n++;
    if (!\$cond) {
        \$fail++;
        fwrite(STDERR, "FAIL: {\$label}\\n");
    }
}

// SKILL.md: обязательные разделы
\$skill = (string)file_get_contents(dirname(__DIR__) . '/SKILL.md');
check((bool)preg_match('/^---\\nname: [a-z0-9-]+\\ndescription: .+\\n(license: .+\\n)?---\\n/', \$skill), 'SKILL.md: frontmatter');
foreach (['Когда использовать', 'Результат', 'Правила агента'] as \$h) {
    check(str_contains(\$skill, '## ' . \$h), "SKILL.md: раздел {\$h}");
}
check(!str_contains(\$skill, '{{'), 'SKILL.md: плейсхолдеры заполнены');
{$testBody}
if (\$fail > 0) {
    fwrite(STDERR, "{\$fail} из {\$n} тестов упали\\n");
    exit(1);
}
echo "OK: {\$n} тестов\\n";

PHP);

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

$next = ["cd {$dir} && git init"];
$next[] = $scripts === []
    ? 'заполнить плейсхолдеры в SKILL.md/README.md (ситуации, правила)'
    : 'заполнить плейсхолдеры в SKILL.md/README.md (ситуации, опции скриптов, правила)';
$next[] = 'положить реальный пример запроса пользователя из истории сессии';
$next[] = 'создать репо на GitHub, включить branch protection (PR обязателен)';

echo json_encode([
    'ok' => true,
    'dir' => $dir,
    'scripts' => array_column($scripts, 'name'),
    'files' => $created,
    'placeholders_left' => $left,
    'next' => $next,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
