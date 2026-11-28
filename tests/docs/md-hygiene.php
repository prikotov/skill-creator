<?php
/**
 * Тесты docs/md-hygiene: маркеры списков во всех документах репозитория.
 * Маркер списка — только с начала строки, иначе это склейка.
 * Запуск: php tests/docs/md-hygiene.php (или через tests/run.php). Код выхода 0/1.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

$root = dirname(__DIR__, 2);

foreach (['SKILL.md', 'README.md', 'references/reports.md', 'references/publish.md', 'references/audit.md', 'references/similar-solutions.md', 'AGENTS.md'] as $doc) {
    $lines = file($root . '/' . $doc, FILE_IGNORE_NEW_LINES);
    $bad = 0;
    foreach ($lines as $ln) {
        // строка списка начинается с "- " (с отступом ≤2) — иначе это склейка
        if (preg_match('~^\s*- [A-ZА-ЯЁ]~u', $ln) === 0 && preg_match('~[.)»]\s+- [A-ZА-ЯЁ]~u', $ln) === 1) {
            $bad++;
        }
    }
    check($bad === 0, "md-hygiene: склеенный маркер списка в {$doc} ({$bad})");
}

testFinish('md-hygiene');
