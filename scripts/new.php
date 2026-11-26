<?php
/**
 * skill-creator: каркас нового скилла.
 *
 *   php scripts/new.php --name my-skill --dir /home/dp/MyProjects/my-skill \
 *       [--title "My Skill"] [--description "..."] [--what "..."] [--owner prikotov] [--script main]
 *
 * Создаёт каталог с шаблонами (SKILL.md, README.md, AGENTS.md, LICENSE, CI,
 * tests/run.php, references/api.md, скрипт-заготовку), подставляя плейсхолдеры.
 * Существующий каталог не трогает (код выхода 1). git init/commit не делает.
 * stdout — JSON со списком файлов и оставшимися плейсхолдерами.
 */
declare(strict_types=1);

const ROOT = __DIR__ . '/..';

/** Разбор аргументов: --key value. */
function argv(array $argv): array
{
    $opt = [];
    $n = count($argv);
    for ($i = 1; $i < $n; $i++) {
        if (str_starts_with($argv[$i], '--') && isset($argv[$i + 1])) {
            $opt[substr($argv[$i], 2)] = $argv[++$i];
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

$opt = argv($argv);
$name = $opt['name'] ?? '';
$dir = $opt['dir'] ?? '';
if (!preg_match('~^[a-z0-9]+(-[a-z0-9]+)*$~', $name)) {
    fail('usage: php scripts/new.php --name kebab-case-name --dir /path/to/new/repo [--title ..] [--description ..] [--what ..] [--owner ..] [--script ..]');
}
if ($dir === '') {
    $dir = dirname(ROOT, 2) . '/' . $name;
}
if (is_dir($dir) && count(scandir($dir)) > 2) {
    fail("каталог не пуст: {$dir}");
}

$title = $opt['title'] ?? ucwords(str_replace('-', ' ', $name));
$description = $opt['description'] ?? 'Что делает skill (кратко, для модели: когда использовать)';
$what = $opt['what'] ?? 'что делает скрипт';
$owner = $opt['owner'] ?? 'prikotov';
$script = $opt['script'] ?? 'main';
// клиент: demo-skill → DemoSkillClient
$client = implode('', array_map(fn($p) => ucfirst($p), explode('-', $name))) . 'Client';

$map = [
    '{{name}}' => $name,
    '{{Title}}' => $title,
    '{{description}}' => $description,
    '{{one-line}}' => $what,
    '{{what}}' => $what,
    '{{script}}' => $script,
    '{{Client}}' => $client,
    '{{owner}}' => $owner,
    '{{opt}}' => 'opt',
    '{{description}}' => $description,
    '{{values}}' => 'val1, val2',
    '{{default}}' => 'val1',
    '{{field}}' => 'items',
    '{{meaning}}' => 'что в поле',
    '{{rule-1}}' => 'правило 1',
    '{{rule-2}}' => 'правило 2',
    '{{fields}}' => 'items[]',
    '{{rule}}' => 'правило разработки',
];

$files = [
    'SKILL.md' => 'SKILL.template.md',
    'README.md' => 'README.template.md',
    'AGENTS.md' => 'AGENTS.template.md',
    '.github/workflows/ci.yml' => 'ci.template.yml',
];
$created = [];
foreach ($files as $rel => $tpl) {
    $out = $dir . '/' . $rel;
    @mkdir(dirname($out), 0777, true);
    $text = file_get_contents(ROOT . '/assets/' . $tpl);
    $text = strtr($text, $map);
    file_put_contents($out, $text);
    $created[] = $rel;
}
// LICENSE — как есть
@mkdir($dir, 0777, true);
copy(ROOT . '/LICENSE', $dir . '/LICENSE');
$created[] = 'LICENSE';

// references/api.md — заготовка
@mkdir($dir . '/references', 0777, true);
file_put_contents($dir . '/references/api.md', "# Механика: {$title} (проверено " . date('Y-m-d') . ")\n\nТолько проверенные факты с датой: поведение сайтов/API, ловушки,\nотрицательные результаты. Сюда — не инструкция для агента (это SKILL.md).\n");
$created[] = 'references/api.md';

// скрипт-заготовка
@mkdir($dir . '/scripts', 0777, true);
$scriptPhp = <<<PHP
<?php
/**
 * {$title}: {$what}.
 *
 *   php scripts/{$script}.php [--opt value]
 *
 * stdout — JSON; прогресс — в stderr. Код выхода 0/1.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/{$client}.php';

\$opt = {$client}::argv(\$argv);
\$result = ['ok' => true, 'items' => []];
echo json_encode(\$result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\\n";

PHP;
file_put_contents($dir . "/scripts/{$script}.php", $scriptPhp);
$created[] = "scripts/{$script}.php";

// клиент-заготовка
$clientPhp = <<<PHP
<?php
/**
 * {$title} — общий клиент.
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

PHP;
file_put_contents($dir . "/{$client}.php", $clientPhp);
$created[] = "{$client}.php";

// tests
@mkdir($dir . '/tests', 0777, true);
$tests = <<<PHP
<?php
/**
 * Офлайн-тесты {$name}: чистые функции; сеть не трогают.
 * Запуск: php tests/run.php. Код выхода 0/1.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/{$client}.php';

\$fail = 0;
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

// argv
[\$pos, \$opt] = {$client}::argv(['x', '--query', 'тест', '--flag'], ['flag']);
check(\$opt['query'] === 'тест', 'argv: значение');
check(\$opt['flag'] === true, 'argv: флаг');
check(\$pos === [], 'argv: позиционных нет');

if (\$fail > 0) {
    fwrite(STDERR, "{\$fail} из {\$n} тестов упали\\n");
    exit(1);
}
echo "OK: {\$n} тестов\\n";

PHP;
file_put_contents($dir . '/tests/run.php', $tests);
$created[] = 'tests/run.php';

// .gitignore
file_put_contents($dir . '/.gitignore', "# Cache & downloads\ndata/\n\n# IDE\n.vscode/\n.idea/\n*.swp\n*~\n\n# OS\n.DS_Store\nThumbs.db\n");
$created[] = '.gitignore';

// оставшиеся плейсхолдеры
$left = [];
foreach ($created as $rel) {
    if (!str_ends_with($rel, '.md')) {
        continue;
    }
    preg_match_all('~\{\{[^}]+\}\}~', (string)file_get_contents($dir . '/' . $rel), $m);
    foreach ($m[0] as $ph) {
        $left[$ph][] = $rel;
    }
}

echo json_encode([
    'ok' => true,
    'dir' => $dir,
    'files' => $created,
    'placeholders_left' => $left,
    'next' => [
        "cd {$dir} && git init && php tests/run.php",
        'заполнить плейсхолдеры в SKILL.md/README.md (реальные ситуации, опции, правила)',
        'положить реальный пример запроса пользователя из истории сессии',
        'создать репо на GitHub, включить branch protection (PR обязателен)',
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
