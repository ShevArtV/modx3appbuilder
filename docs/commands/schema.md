# schema — Работа с XML-схемой

Генерирует PHP-классы из XML-схемы базы данных и управляет таблицами.

## Использование

```bash
modxapp schema <name> [options]
```

## Опции

| Опция | Описание |
|-------|----------|
| `--validate` | Только валидировать схему без генерации |
| `--verbose` | Подробный вывод |

## Примеры

```bash
# Сгенерировать классы и обновить таблицы
modxapp schema mypackage

# Только валидация
modxapp schema mypackage --validate
```

## XML-схема

Файл схемы по умолчанию: `core/components/<name>/schema/<name>.mysql.schema.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<model package="Mypackage\Model"
       baseClass="xPDO\Om\xPDOObject"
       platform="mysql"
       defaultEngine="InnoDB"
       version="3.0">

    <object class="MyItem" table="my_items" extends="xPDO\Om\xPDOSimpleObject">
        <field key="name" dbtype="varchar" precision="255"
               phptype="string" null="false" default=""/>
        <field key="description" dbtype="text"
               phptype="string" null="true"/>
        <field key="active" dbtype="tinyint" precision="1"
               phptype="boolean" null="false" default="1"/>
        <field key="createdon" dbtype="datetime"
               phptype="datetime" null="true"/>

        <index alias="name" name="name" primary="false" unique="false" type="BTREE">
            <column key="name" length="" collation="A" null="false"/>
        </index>
    </object>
</model>
```

## Процесс генерации

1. Читает XML-схему
2. Генерирует PHP-классы в `core/components/<name>/src/` (через `parseSchema` MODX 3)
3. Если `update_tables: true` в конфиге — создаёт/обновляет таблицы в БД

## Валидация

```bash
modxapp schema mypackage --validate
```

Проверяет:

- Корректность XML-структуры
- Наличие обязательных атрибутов
- Правильность типов данных

Возвращает список ошибок или сообщение `Schema is valid`.

## Настройки в config.php

```php
'schema' => [
    'file' => 'schema/mypackage.mysql.schema.xml',
    'auto_generate_classes' => true,    // генерировать классы
    'update_tables' => true,            // создавать/обновлять таблицы
],
```
