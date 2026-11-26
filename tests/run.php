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

// 1. имя не kebab-case → отказ
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/new.php') . ' --name Bad_Name --dir ' . escapeshellarg($tmp . '/x') . ' 2>/dev/null', $o, $rc);
check($rc === 1, 'new: имя не kebab-case — код 1');

// 2. каркас создаётся
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/new.php') . ' --name demo-skill --dir ' . escapeshellarg($dir) . ' --what "демо" 2>/dev/null', $o, $rc);
$d = json_decode(implode("\n", $o), true);
check($rc === 0 && ($d['ok'] ?? false) === true, 'new: каркас создан');
foreach (['SKILL.md', 'README.md', 'AGENTS.md', 'LICENSE', '.github/workflows/ci.yml', 'references/api.md', 'scripts/main.php', 'DemoSkillClient.php', 'tests/run.php', '.gitignore'] as $f) {
    check(is_file($dir . '/' . $f), "new: есть {$f}");
}
check(is_array($d['placeholders_left'] ?? null) && count($d['placeholders_left']) > 0, 'new: плейсхолдеры перечислены');
check(str_contains((string)file_get_contents($dir . '/README.md'), 'Skill для AI-агентов') || str_contains((string)file_get_contents($dir . '/README.md'), 'skill для AI-агентов'), 'new: README говорит, что это skill');
check(!str_contains((string)file_get_contents($dir . '/SKILL.md'), '{{name}}'), 'new: name подставлен');

// 3. непустой каталог не трогается
$o = [];
exec('php ' . escapeshellarg($root . '/scripts/new.php') . ' --name demo-skill --dir ' . escapeshellarg($dir) . ' 2>/dev/null', $o, $rc);
check($rc === 1, 'new: непустой каталог — код 1');

// 4. каркас валиден: php -l и свои тесты
foreach (['DemoSkillClient.php', 'scripts/main.php', 'tests/run.php'] as $f) {
    $o = [];
exec('php -l ' . escapeshellarg($dir . '/' . $f) . ' 2>&1', $o, $rc);
    check($rc === 0, "carcass: php -l {$f}");
}
$o = [];
exec('php ' . escapeshellarg($dir . '/tests/run.php') . ' 2>&1', $o, $rc);
check($rc === 0, 'carcass: свои тесты зелёные: ' . implode(' ', $o));
$o = [];
exec('php ' . escapeshellarg($dir . '/scripts/main.php') . ' 2>&1', $o, $rc);
$m = json_decode(implode("\n", $o), true);
check($rc === 0 && ($m['ok'] ?? false) === true, 'carcass: скрипт отдаёт JSON');

// 5. шаблоны без незакрытых плейсхолдеров-опечаток
foreach (glob($root . '/assets/*') as $tpl) {
    preg_match_all('~\{\{([^}]+)\}~', (string)file_get_contents($tpl), $mm);
    foreach ($mm[1] as $ph) {
        check(trim($ph) !== '', 'assets: пустой плейсхолдер в ' . basename($tpl));
    }
}

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
