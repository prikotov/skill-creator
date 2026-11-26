---
name: {{name}}
description: {{description}}
license: {{license}}
---

# {{Title}}

Skill для AI-агентов.

## Когда использовать

- {{situation-1}}
- {{situation-2}}

## Как использовать

Пути — от каталога с SKILL.md.

{{#scripts}}
**Шаг N: {{what}}**

```bash
php scripts/{{script}}.php [опции]
```

Опции:

| Опция | Описание | Значения | По умолчанию |
|-------|----------|----------|--------------|
| `--opt` | {{opt-description}} | {{values}} | {{default}} |

Примеры:

```bash
# {{example-1}}
php scripts/{{script}}.php
```

Результат:

| Поле | Что значит |
|------|------------|
| `ok` | Запрос прошёл |
| `{{field}}` | {{meaning}} |

{{/scripts}}
## Результат

stdout — JSON; прогресс и предупреждения — в stderr. Код выхода 0/1.
Что и где получаем: {{result}}

## Правила агента

- {{rule-1}}
- {{rule-2}}
