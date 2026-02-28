# ComponentBuilder for MODX Revolution 3

Универсальный сборщик транспортных пакетов для MODX Revolution 3 с расширенными возможностями.

## Установка

1. Разместите папку `package_builder` в корне вашего MODX проекта
2. Установите зависимости:
   ```bash
   cd package_builder
   composer install
   ```

## Порядок работы

### 1. Создание пакета

```bash
php cli.php create mypackage
```

Создается базовая структура пакета с конфигурацией и основными файлами.

### 2. Создание таблиц и моделей (если нужны)

Если компоненту нужны таблицы в базе данных:

1. Заполните XML схему: `core/components/mypackage/schema/mypackage.mysql.schema.xml`
2. Запустите команду для создания таблиц и классов:

```bash
php cli.php schema mypackage
```

Эта команда создаст таблицы в БД и сгенерирует PHP классы моделей.

### 3. Написание бизнес-логики

Разрабатывайте основной код компонента в `core/components/mypackage/src/`.

### 4. Управление элементами

По ходу разработки создавайте элементы MODX:

```bash
php cli.php elements mypackage
```

Команда создаст/обновит элементы (chunks, snippets, plugins, templates, TVs, settings, menus, events) на основе файлов в `elements/`.

### 5. Извлечение лексиконов и настроек

Автоматически извлекайте строки локализации и системные настройки из кода:

```bash
php cli.php extract-lexicons mypackage
php cli.php extract-settings mypackage
```

### 6. Сборка пакета

Когда разработка завершена:

```bash
php cli.php build mypackage
```

Создаст transport.zip готовый к установке.

## Использование

### CLI интерфейс

```bash
# Создать шаблон нового пакета
php cli.php create mypackage

# Создать таблицы и классы моделей из XML схемы
php cli.php schema mypackage

# Управление элементами (создание/обновление)
php cli.php elements mypackage

# Извлечь лексиконы из кода
php cli.php extract-lexicons mypackage

# Извлечь настройки из кода
php cli.php extract-settings mypackage

# Собрать пакет для дистрибуции
php cli.php build mypackage

# Справка
php cli.php help
```

### Web интерфейс

Сборка пакета через HTTP-запрос (для серверов без CLI PHP 8):

```
build_web.php?package=mypackage
build_web.php?package=mypackage&install
build_web.php?package=mypackage&download
build_web.php?package=mypackage&encrypt
```

### Опции команд

**Build** (сборка):

| Опция | Описание |
|-------|----------|
| `--install` | Установить пакет после сборки |
| `--download` | Скачать transport.zip после сборки (web) |
| `--encrypt` | Включить шифрование пакета через modstore.pro |

**Create** (создание):

| Опция | Описание |
|-------|----------|
| `--elements` | Генерировать файлы элементов |
| `--author=` | Имя автора |
| `--email=` | Email автора |
| `--gitlogin=` | Git username (GitHub/GitLab) |
| `--repository=` | URL репозитория |
| `--short-name=` | Краткое имя для лексиконов/настроек |
| `--php-version=` | Минимальная версия PHP (по умолчанию 8.1) |
| `--interactive` | Интерактивный режим |

**Schema** (схема):

| Опция | Описание |
|-------|----------|
| `--validate` | Проверить схему без генерации классов |

**General** (общие):

| Опция | Описание |
|-------|----------|
| `--verbose` | Подробный вывод |

### Интерактивный режим

Интерактивный режим позволяет пошагово ввести все необходимые данные:

```bash
php cli.php create --interactive
```

Пример диалога:
```
=== ComponentBuilder Interactive Mode ===
Let's create your MODX 3 package step by step.

Package name (lowercase, no spaces): mypackage
Author name [Your Name]: John Doe
Author email [your-email@example.com]: john@example.com
Git login (GitHub/GitLab username) [your-username]: johndoe
Short component name (for lexicons and settings) [myp]: my
Minimum PHP version [8.1]: 8.2
Repository URL []: https://github.com/johndoe/mypackage
Generate elements files? [Y/n]: y
```

### Конфигурационный файл пользователя

Создается файл `user.config.json` в папке `package_builder` для хранения параметров по умолчанию:

```json
{
  "author": "Your Name",
  "email": "your-email@example.com",
  "gitlogin": "your-username",
  "repository": "https://github.com/your-username",
  "phpVersion": "8.1",
  "generateElements": true
}
```

В неинтерактивном режиме значения из `user.config.json` используются как fallback для CLI опций. В интерактивном режиме — как значения по умолчанию в промптах.

## Структура пакета

### Создание пакета

Создание нового пакета с базовой структурой:

```bash
php cli.php create mypackage --elements
```

Команда создаст структуру пакета:

```
core/components/mypackage/
├── bootstrap.php                         # Файл инициализации
├── composer.json                         # Зависимости и автозагрузка
├── docs/
│   ├── readme.txt                        # Описание компонента
│   ├── license.txt                       # Лицензия MIT
│   └── changelog.txt                     # История изменений
├── elements/                             # (если указан --elements)
│   ├── plugins/
│   │   └── switch.php                    # Единая точка входа плагинов
│   └── snippets/
│       └── connector.php                 # Коннектор для AJAX
├── lexicon/
│   ├── ru/default.inc.php                # Русский язык
│   └── en/default.inc.php                # Английский язык
├── schema/
│   └── mypackage.mysql.schema.xml        # XML схема БД (пустая)
└── src/
    ├── Mypackage.php                     # Основной класс
    └── Plugins/
        └── IPlugin.php                   # Интерфейс плагинов

packages/mypackage/
├── config.php                            # Конфигурация пакета
└── elements/                             # (если указан --elements)
    ├── plugins.php                       # Определения плагинов
    └── snippets.php                      # Определения сниппетов
```

### Базовая структура для сборки

Файлы элементов в `packages/mypackage/elements/` ссылаются из конфигурации. Создаются автоматически только `plugins.php` и `snippets.php`. Остальные файлы нужно создать вручную по мере необходимости:

```
packages/mypackage/
├── config.php                 # Конфигурация пакета
└── elements/
    ├── chunks.php             # Чанки
    ├── snippets.php           # Сниппеты
    ├── plugins.php            # Плагины
    ├── templates.php          # Шаблоны
    ├── tvs.php                # TV параметры
    ├── settings.php           # Системные настройки
    ├── menus.php              # Меню админки
    ├── events.php             # Кастомные события
    ├── policies.php           # Политики доступа
    └── policyTemplates.php    # Шаблоны политик
```

## Конфигурация пакета

Файл `packages/mypackage/config.php`:

```php
<?php

return [
    'name' => 'MyPackage',
    'name_lower' => 'mypackage',
    'name_short' => 'my',
    'version' => '1.0.0',
    'release' => 'alpha',

    'paths' => [
        'core' => 'core/components/mypackage/',
        'assets' => 'assets/components/mypackage/',
    ],

    'schema' => [
        'file' => 'model/schema/mypackage.mysql.schema.xml',
        'auto_generate_classes' => true,
        'update_tables' => true,
    ],

    'elements' => [
        'category' => 'MyPackage',
        'chunks' => 'elements/chunks.php',
        'snippets' => 'elements/snippets.php',
        'plugins' => 'elements/plugins.php',
        'templates' => 'elements/templates.php',
        'tvs' => 'elements/tvs.php',
        'settings' => 'elements/settings.php',
        'menus' => 'elements/menus.php',
    ],

    'static' => [
        'chunks' => true,
        'snippets' => true,
        'templates' => true,
    ],

    'build' => [
        'download' => false,
        'install' => false,
        'update' => [
            'chunks' => true,
            'snippets' => true,
            'settings' => false,
        ],
    ],

    // Шифрование (опционально)
    // 'encrypt' => true,
    // 'encrypt_vehicle_class' => 'MyPackage\\Transport\\EncryptedVehicle',

    // Кастомный путь к резолверам (опционально)
    // 'resolvers_path' => '_build/resolvers/',

    // Минимальная версия PHP (опционально)
    // 'php_version' => '8.1',
];
```

**Использование краткого имени в лексиконах:**
- Если `name_short` указан: `my_user_created`
- Если не указан: `mypackage_user_created`

## Элементы

### Чанки (elements/chunks.php)

```php
<?php

return [
    'tpl.mypackage.example' => [
        'description' => 'Example chunk',
        'content' => 'file:chunks/example.tpl',
    ],
];
```

Префикс `file:` в `content` загружает содержимое из файла относительно `core/components/mypackage/`.

### Сниппеты (elements/snippets.php)

```php
<?php

return [
    'MyPackageSnippet' => [
        'description' => 'Example snippet',
        'content' => 'file:snippets/mypackagesnippet.php',
        'properties' => [
            'limit' => [
                'type' => 'textfield',
                'value' => '10',
                'desc' => 'Limit results',
            ],
        ],
    ],
];
```

### Плагины (elements/plugins.php)

```php
<?php

return [
    'MyPackagePlugin' => [
        'description' => 'Example plugin',
        'content' => 'file:elements/plugins/switch.php',
        'events' => [
            // Полный формат
            'OnPageNotFound' => [
                'priority' => 0,
                'propertyset' => 0,
            ],
            // Краткий формат (только имя события)
            'OnLoadWebDocument',
        ],
    ],
];
```

### Шаблоны (elements/templates.php)

```php
<?php

return [
    'MyPackageTemplate' => [
        'description' => 'Example template',
        'content' => 'file:templates/mytemplate.tpl',
    ],
];
```

### TV параметры (elements/tvs.php)

```php
<?php

return [
    'my_image' => [
        'caption' => 'Image',
        'description' => 'Product image',
        'type' => 'image',
        'default' => '',
        'elements' => '',
    ],
];
```

### Системные настройки (elements/settings.php)

```php
<?php

return [
    'mypackage_api_timeout' => [
        'value' => '30',
        'xtype' => 'textfield',
        'area' => 'mypackage',
    ],
];
```

### Меню (elements/menus.php)

```php
<?php

return [
    'mypackage' => [
        'description' => 'mypackage_menu_desc',
        'action' => 'home',
        'parent' => 'components',
        'icon' => '',
        'menuindex' => 0,
        'params' => '',
        'handler' => '',
    ],
];
```

### События (elements/events.php)

```php
<?php

return [
    'OnMyPackageBeforeSave',
    'OnMyPackageAfterSave',
    'OnMyPackageBeforeRemove',
];
```

### Политики доступа (elements/policies.php)

```php
<?php

return [
    'MyPackagePolicy' => [
        'description' => 'Custom policy',
        'data' => [
            'mypackage_view' => true,
            'mypackage_edit' => true,
        ],
    ],
];
```

### Шаблоны политик (elements/policyTemplates.php)

```php
<?php

return [
    'MyPackagePolicyTemplate' => [
        'description' => 'Policy template',
        'permissions' => [
            'mypackage_view' => [
                'description' => 'View items',
                'value' => true,
            ],
            'mypackage_edit' => [
                'description' => 'Edit items',
                'value' => true,
            ],
        ],
    ],
];
```

## Извлечение лексиконов

Автоматическое извлечение строк для локализации из PHP кода в `src/`:

```bash
php cli.php extract-lexicons mypackage
```

Найдет все вызовы `$modx->lexicon('key')` и создаст файл лексикона.

**Правила для ключей лексиконов:**
- Использовать только нижнее подчеркивание как разделитель слов
- Формат: `prefix_action_description`
- Запрещено использовать дефисы и другие разделители
- Рекомендуется использовать краткое имя пакета для лучшей читаемости

**Примеры правильных ключей:**
```php
$modx->lexicon('my_user_created');
$modx->lexicon('my_error_not_found');
$modx->lexicon('my_settings_saved');
```

**Примеры неправильных ключей:**
```php
$modx->lexicon('mypackage-user-created');  // дефис
$modx->lexicon('mypackage.user.created');  // точка
$modx->lexicon('mypackageUserCreated');    // camelCase
```

## Извлечение настроек

Автоматическое извлечение системных настроек из кода в `src/`:

```bash
php cli.php extract-settings mypackage
```

Найдет все вызовы `$modx->getOption('prefix_setting')` и сгенерирует файл настроек `elements/settings.php`.

**Примеры правильных ключей:**
```php
$modx->getOption('my_api_timeout');
$modx->getOption('my_debug_mode');
```

## XML схемы и работа с БД

### Создание таблиц из схемы

```bash
php cli.php schema mypackage
```

Эта команда:
1. Читает XML схему из пути, указанного в `config.php` (секция `schema.file`)
2. Генерирует PHP классы моделей в `core/components/mypackage/src/`
3. Создает/обновляет таблицы в базе данных

### Валидация схемы

```bash
php cli.php schema mypackage --validate
```

Проверяет корректность XML схемы без генерации классов и таблиц.

### Структура XML схемы

Пример файла схемы для MODX 3:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<model package="mypackage" baseClass="xPDO\Om\xPDOObject" platform="mysql" defaultEngine="InnoDB" version="3.0">
   <object class="MyItem" table="mypackage_items" extends="xPDO\Om\xPDOSimpleObject">
      <field key="name" dbtype="varchar" precision="255" phptype="string" null="false" index="index" />
      <field key="description" dbtype="text" phptype="string" null="true" />
      <field key="createdon" dbtype="datetime" phptype="datetime" null="false" index="index" />
      <field key="active" dbtype="tinyint" precision="1" attributes="unsigned" phptype="integer" null="false" default="1" index="index" />

      <index alias="name" name="name" primary="false" unique="false" type="BTREE">
         <column key="name" length="" collation="A" null="false" />
      </index>
      <index alias="createdon" name="createdon" primary="false" unique="false" type="BTREE">
         <column key="createdon" length="" collation="A" null="false" />
      </index>
   </object>
</model>
```

**Пример сложной схемы со связями:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<model package="mypackage" baseClass="xPDO\Om\xPDOObject" platform="mysql" defaultEngine="InnoDB" version="3.0">
   <object class="msCategory" table="ms3_categories" extends="MODX\Revolution\modResource">
      <field key="class_key" dbtype="varchar" precision="100" phptype="string" null="false" default="mypackage\\Model\\msCategory"/>
      <aggregate alias="Products" class="mypackage\\Model\\msProduct" local="id" foreign="parent" cardinality="many" owner="local"/>
   </object>

   <object class="msProduct" table="ms3_products" extends="MODX\Revolution\modResource">
      <field key="class_key" dbtype="varchar" precision="100" phptype="string" null="false" default="mypackage\\Model\\msProduct"/>
      <aggregate alias="Category" class="mypackage\\Model\\msCategory" local="parent" foreign="id" cardinality="one" owner="foreign"/>
      <aggregate alias="Data" class="mypackage\\Model\\msProductData" local="id" foreign="id" cardinality="one" owner="local"/>
   </object>
</model>
```

**Особенности схемы MODX 3:**
- `package="mypackage"` - пространство имен для классов
- `baseClass="xPDO\Om\xPDOObject"` - базовый класс для всех объектов
- `platform="mysql"` - указание СУБД
- `defaultEngine="InnoDB"` - движок таблиц по умолчанию
- `extends="xPDO\Om\xPDOSimpleObject"` - для объектов с автоинкрементным ID
- `extends="xPDO\Om\xPDOObject"` - для объектов с указанным ID
- **Связи:** `composite` (композиция) и `aggregate` (агрегация)
- **Индексы:** для оптимизации запросов

### Настройки схемы в конфигурации

```php
'schema' => [
    'file' => 'model/schema/mypackage.mysql.schema.xml',
    'auto_generate_classes' => true,
    'update_tables' => true,
],
```

## Шифрование пакетов

ComponentBuilder поддерживает шифрование транспортных пакетов через API modstore.pro (AES-256-CBC).

### Использование

**CLI:**
```bash
php cli.php build mypackage --encrypt
```

**Web:**
```
build_web.php?package=mypackage&encrypt
```

**Через конфиг:**
```php
'encrypt' => true,
```

### Подготовка

1. Скопируйте `encryption/EncryptedVehicle.php` в `core/components/mypackage/src/Transport/`
2. Замените namespace на ваш: `namespace MyPackage\Transport;`
3. Скопируйте `encryption/resolve.encryption.php` в `core/components/mypackage/resolvers/`
4. Настройте провайдер modstore.pro в админке MODX

Подробная документация: `encryption/README.md`

## Архитектура

### Классы

| Класс | Описание |
|-------|----------|
| `CLI` | Парсинг CLI аргументов, интерактивный режим |
| `ComponentBuilder` | Сборка transport.zip, шифрование, установка |
| `ConfigManager` | Загрузка конфигурации, создание пакетов из шаблонов |
| `ElementManager` | Синхронизация элементов в БД (команда `elements`) |
| `ElementsManager` | Упаковка элементов в transport package (команда `build`) |
| `SchemaManager` | Генерация классов и таблиц из XML схемы |
| `LexiconExtractor` | Извлечение лексиконов из кода |
| `SettingsExtractor` | Извлечение настроек из кода |
| `ElementType` | Enum типов элементов |

### Поддерживаемые элементы

**Команда `elements`** (синхронизация в БД): chunks, snippets, plugins, templates, TVs, settings, menus, events

**Команда `build`** (упаковка в пакет): chunks, snippets, plugins, templates, TVs, settings, menus, events, policies, policyTemplates

## Особенности

- **Пошаговый процесс разработки** - создаем пакет, добавляем таблицы, пишем логику, управляем элементами
- **Автоматическая генерация классов моделей** из XML схем
- **Управление элементами** - отдельная команда для синхронизации с БД
- **Извлечение лексиконов и настроек** из кода
- **Шифрование пакетов** через modstore.pro API (AES-256-CBC)
- **Web интерфейс** для сборки на серверах без CLI PHP 8
- **Интерактивный режим** создания пакетов с сохранением параметров
- **Шаблонная система** генерации файлов с плейсхолдерами
- **Валидация XML схем** без генерации

## Требования

- PHP 8.1+
- MODX Revolution 3.x
- Composer

## Лицензия

MIT
