<?php
/**
 * skill-creator: подсчёт hash навыка по процедуре references/package.md.
 *
 *   php scripts/hash.php <каталог-навыка>           # напечатать hash
 *   php scripts/hash.php --write <каталог-навыка>   # вписать в SKILL.md
 *
 * hash — sha256 по файлам навыка; имена файлов сортируют, содержимое
 * склеивают. Строки hash: в файлах навыка при подсчёте заменяют на соль
 * «hash: package» — иначе значение зависело бы от самого себя: hash пишется
 * и в SKILL.md, и в записи CHANGELOG.
 * Каталоги .git/ и data/ в подсчёт не входят.
 * stdout — JSON; ошибки — в stderr. Код выхода 0/1.
 * Файлы только читает; в режиме --write перезаписывает строку hash:
 * во frontmatter SKILL.md (строки hash: в теле — примеры доков — не трогает).
 */
declare(strict_types=1);

function fail(string $msg): never
{
    fwrite(STDERR, $msg . "\n");
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE), "\n";
    exit(1);
}

$dir = $argv[1] ?? '';
$write = false;
if ($dir === '--write') {
    $write = true;
    $dir = $argv[2] ?? '';
}
if ($dir === '' || !is_dir($dir) || !is_readable($dir)) {
    fail('usage: php scripts/hash.php [--write] <каталог-навыка>');
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile()) {
        continue;
    }
    $path = $f->getPathname();
    if (str_contains($path, '/.git/') || str_contains($path, '/data/')) {
        continue;
    }
    $files[] = $path;
}
sort($files);
if ($files === []) {
    fail("в каталоге нет файлов: {$dir}");
}

$blob = '';
foreach ($files as $path) {
    $content = (string)file_get_contents($path);
    $content = preg_replace('~^hash: .*$~m', 'hash: package', $content);
    $blob .= $content;
}
$hash = hash('sha256', $blob);

if ($write) {
    $skillFile = $dir . '/SKILL.md';
    if (!is_file($skillFile)) {
        fail("нет SKILL.md: {$skillFile}");
    }
    $skill = (string)file_get_contents($skillFile);
    // Замена только во frontmatter: строки hash: в теле SKILL.md — примеры доков.
    if (!preg_match('~\A---\n(.*?)\n---~s', $skill, $m)) {
        fail('в SKILL.md нет frontmatter');
    }
    if (preg_match('~^hash: ~m', $m[1]) !== 1) {
        fail('в SKILL.md нет строки hash:');
    }
    $fm = preg_replace('~^hash: .*$~m', 'hash: ' . $hash, $m[1]);
    $skill = "---\n" . $fm . "\n---" . substr($skill, strlen($m[0]));
    if (file_put_contents($skillFile, $skill) === false) {
        fail("SKILL.md не записан: {$skillFile}");
    }
}

echo json_encode([
    'ok' => true,
    'hash' => $hash,
    'files' => count($files),
    'written' => $write,
], JSON_UNESCAPED_UNICODE), "\n";
