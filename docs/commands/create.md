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

### Структура компонента

```
core/components/mypackage/
├── bootstrap.php              — точка входа
├── composer.json              — зависимости (PHPStan всегда включён)
├── phpstan.neon               — конфигурация PHPStan (level 5)
├── .php-cs-fixer.dist.php     — конфигурация CS Fixer (если включён)
├── eslint.config.js           — конфигурация ESLint (если включён)
├── package.json               — npm зависимости (если ESLint включён)
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

### Конфигурация сборки

```
packages/mypackage/
├── config.php
└── elements/
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

## Конфиг пользователя

После первого `--interactive` создаётся `user.config.json` с введёнными данными. При следующем создании эти значения используются как значения по умолчанию.
