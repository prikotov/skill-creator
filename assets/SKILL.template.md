---
name: {{name}}
description: {{description}}
license: {{license}}
# необязательное поле; нет зависимостей — удали строку
depends_on: []
package: https://github.com/{{owner}}/{{name}}
version: 0.1.0
updated: {{updated}}
# pending; пересчитай после заполнения плейсхолдеров
hash: pending
---

# {{Title}}

Этот навык для {{for-whom}}.

## Когда использовать

- {{situation-1}}
- {{situation-2}}
- {{situation-3}}

## Как использовать

`<skill-dir>` — каталог этого SKILL.md, подставь его в команду.

**Шаг 1: {{step-1}}**

{{instruction-1}}

```bash
php <skill-dir>/scripts/{{script-1}}.php --{{option-1}} {{option-1-value}}
```

| Опция | Описание | Значения | По умолчанию |
|-------|----------|----------|--------------|
| `--{{option-1}}` | {{option-1-desc}} | {{option-1-values}} | {{option-1-default}} |

Результат шага: {{step-1-result}}.

```bash
# {{example-1-comment}}
php <skill-dir>/scripts/{{script-1}}.php {{example-1-args}}
```

**Шаг 2: {{step-2}}**

{{instruction-2}}

## Результат

stdout — JSON; прогресс и предупреждения — в stderr. Код выхода 0/1.
Что и где получаем: {{result}}.

| Поле | Что значит |
|------|------------|
| {{field-1}} | {{field-1-desc}} |

## Правила для агента

- {{rule-1}}
- Действия при выходе за границы навыка: {{rule-out-of-bounds}}.
