# create — Создание пакета

Создаёт структуру нового компонента из шаблонов.

## Использование

```bash
modxapp create <name> [options]
```

## Опции

| Опция | Описание | По умолчанию |
|-------|----------|-------------|
| `--interactive` | Интерактивный режим | — |
| `--elements` | Генерировать файлы элементов | `false` |
| `--author=<name>` | Имя автора | из `user.config.json` |
| `--email=<email>` | Email автора | из `user.config.json` |
| `--gitlogin=<login>` | Git username | из `user.config.json` |
| `--repository=<url>` | URL репозитория | — |
| `--short-name=<name>` | Краткое имя (для лексиконов) | первые 3 символа имени |
| `--php-version=<ver>` | Минимальная версия PHP | `8.1` |
| `--no-tests` | Не генерировать PHPUnit и test-utils | `false` |
| `--php-cs-fixer` | Добавить PHP CS Fixer | `false` |
| `--eslint` | Добавить ESLint для JS | `false` |
| `--template=<path>` | Путь к [кастомным шаблонам](templates.md) | оригинальные |

## Примеры

```bash
# Интерактивный режим
modxapp create mypackage --interactive

# С флагами
modxapp create mypackage --elements --author="Ivan" --email=ivan@test.com

# Со всеми инструментами
modxapp create mypackage --elements --php-cs-fixer --eslint

# С кастомными шаблонами
modxapp create mypackage --template=./my-templates
```

## Что создаётся

При использовании встроенного шаблона `default` создаётся следующая структура. При использовании [кастомного шаблона](templates.md) структура может отличаться.

### Исходники компонента (`core/components/<name>/`)

```text
core/components/mypackage/
├── bootstrap.php              — точка входа, регистрация namespace
├── composer.json              — зависимости (PHPStan всегда включён)
├── phpstan.neon               — конфигурация PHPStan (level 5)
├── phpunit.xml                — конфигурация PHPUnit (убирается --no-tests)
├── .php-cs-fixer.dist.php     — конфигурация CS Fixer (если включён)
├── eslint.config.js           — конфигурация ESLint (если включён)
├── package.json               — npm зависимости (если ESLint включён)
├── tests/                     — убирается --no-tests
│   └── ExampleTest.php        — пример unit-теста с моком MODX
├── docs/
│   ├── readme.txt
│   ├── license.txt
│   └── changelog.txt
├── lexicon/
│   ├── en/default.inc.php
│   └── ru/default.inc.php
├── schema/
│   └── mypackage.mysql.schema.xml
├── src/
│   ├── Mypackage.php          — главный класс
│   └── Plugins/
│       └── IPlugin.php        — интерфейс плагинов
└── elements/                  — только если --elements
    ├── plugins/
    │   └── switch.php         — единая точка входа плагинов
    └── snippets/
        └── connector.php
```

### Публичные файлы (`assets/components/<name>/`)

```text
assets/components/mypackage/
├── css/
│   ├── mgr/mypackage.css      — стили для админки
│   └── web/mypackage.css      — стили для фронтенда
└── js/
    ├── mgr/mypackage.js       — скрипты для админки
    └── web/mypackage.js       — скрипты для фронтенда
```

### Конфигурация сборки (`package_builder/packages/<name>/`)

```text
package_builder/packages/mypackage/
├── config.php                 — конфигурация пакета
└── elements/                  — описание элементов для сборки
    ├── plugins.php
    └── snippets.php
```

## Плейсхолдеры в шаблонах

При создании пакета в файлах-шаблонах заменяются плейсхолдеры:

| Плейсхолдер | Значение | Пример |
|-------------|----------|--------|
| `{{package_name}}` | Имя в lowercase | `mypackage` |
| `{{Package_name}}` | Имя с заглавной | `Mypackage` |
| `{{short_name}}` | Краткое имя | `my` |
| `{{author_name}}` | Имя автора | `Ivan Petrov` |
| `{{author_email}}` | Email | `ivan@test.com` |
| `{{php_version}}` | Версия PHP | `8.1` |
| `{{gitlogin}}` | Git username | `ivanpetrov` |
| `{{repository}}` | URL репозитория | `https://github.com/...` |
| `{{current_year}}` | Текущий год | `2026` |
| `{{current_date}}` | Текущая дата | `2026-03-22` |
| `{{builder_test_utils_path}}` | Путь к test-utils | `../../../package_builder/test-utils` |

