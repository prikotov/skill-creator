# Публикация

Публикация навыка: репозиторий на GitHub, затем заполнение `package`
в frontmatter SKILL.md.

## Репозиторий на GitHub

- description начинается со «Навык для ИИ-агентов: ...».
- topics: `skill`, `skill-md`, `ai-agents`, `php` и по содержанию.
- branch protection `main`: прямые коммиты запрещены, PR обязателен.
- тесты зелёные: `php -l` и `php tests/run.php`.

## Заполнение package

После создания репозитория заполни в frontmatter SKILL.md `package`,
`version`, `hash` — процедура в `package.md`.
