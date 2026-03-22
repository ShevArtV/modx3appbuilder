# schema-extract — Извлечение схемы из БД

Генерирует XML-схему MODX 3 из существующих таблиц в базе данных. После извлечения автоматически генерирует PHP-классы.

## Использование

```bash
modxapp schema-extract <name>
```

## Пример

```bash
modxapp schema-extract mypackage
```

Результат:

```
Extracted 3 tables to core/components/mypackage/schema/mypackage.mysql.schema.xml
Classes generated
```

## Как работает

1. Ищет таблицы с префиксом `modx_<name>_` в базе данных
2. Читает структуру каждой таблицы: поля, типы, индексы
3. Генерирует XML-схему в `core/components/<name>/schema/<name>.mysql.schema.xml`
4. Автоматически генерирует PHP-классы в `core/components/<name>/src/`

## Имена классов

Имя класса для каждой таблицы определяется по приоритету:

1. **TABLE_COMMENT** — если комментарий таблицы в MySQL содержит имя в PascalCase (например `MyItem`), оно используется как имя класса
2. **Имя таблицы** — если комментарий пустой или не в PascalCase, имя генерируется из имени таблицы: `mypackage_items` → `Items`

Чтобы задать имя класса через комментарий:

```sql
ALTER TABLE modx_mypackage_items COMMENT = 'MyItem';
```

## Маппинг типов

| MySQL тип | phptype |
|-----------|---------|
| `int`, `bigint`, `smallint`, `mediumint` | `integer` |
| `tinyint(1)` | `boolean` |
| `tinyint` (не 1) | `integer` |
| `float`, `double`, `decimal` | `float` |
| `varchar`, `char`, `text`, `mediumtext`, `longtext` | `string` |
| `datetime` | `datetime` |
| `timestamp` | `timestamp` |
| `date` | `date` |
| `json` | `json` |

## Автоопределение

- Таблица с `AUTO_INCREMENT` на поле `id` → `extends="xPDO\Om\xPDOSimpleObject"` (поле `id` не включается в схему)
- Таблица без `AUTO_INCREMENT` → `extends="xPDO\Om\xPDOObject"`
- `UNSIGNED` → `attributes="unsigned"`
- Уникальные индексы → `unique="true"`

## Когда использовать

- Миграция существующей базы данных в компонент MODX 3
- Таблицы создавались вручную через SQL
- Нужно быстро получить схему из рабочей БД

!!! warning "Требует полный MODX"
    Команда работает только с установленным MODX и подключением к MySQL. В headless-режиме недоступна.

!!! tip "Проверьте результат"
    После извлечения откройте XML-схему и проверьте имена классов, типы полей и связи. Связи (`composite`/`aggregate`) между таблицами не извлекаются автоматически — их нужно добавить вручную.
