---
name: {{name}}
description: {{description}}
license: MIT
---

# {{Title}}

Skill для AI-агентов: {{one-line}}.

## Когда использовать

- {{situation-1}}
- {{situation-2}}

## Запуск

Из корня репо скилла; после установки в проект путь будет
`.agents/skills/{{name}}/scripts/`.

```bash
# {{what-example-1}}
php scripts/{{script}}.php

# {{what-example-2}}
php scripts/{{script}}.php {{options-example}}
```

### Опции {{script}}.php

| Опция | Описание | Значения | По умолчанию |
|-------|----------|----------|--------------|
| `--{{opt}}` | {{description}} | {{values}} | {{default}} |

## Результат

stdout — JSON; прогресс и предупреждения — в stderr. Код выхода 0/1.

| Поле | Что значит |
|------|------------|
| `ok` | Запрос прошёл |
| `{{field}}` | {{meaning}} |

## Правила агента

- {{rule-1}}
- {{rule-2}}
- Механика API и ловушки — `references/api.md`, не здесь.
