# templates — Управление шаблонами

Позволяет просматривать и копировать оригинальные шаблоны для кастомизации.

## Использование

```bash
modxapp templates path              # показать путь к оригинальным шаблонам
modxapp templates copy <path>       # скопировать шаблоны в указанную папку
```

## Примеры

```bash
# Узнать, где лежат оригинальные шаблоны
modxapp templates path

# Скопировать для кастомизации
modxapp templates copy ./my-templates
```

## Кастомные шаблоны

### Workflow

```bash
# 1. Скопировать оригинальные шаблоны
modxapp templates copy ./my-templates

# 2. Отредактировать под свои нужды
#    ./my-templates/components/   — шаблоны компонента
#    ./my-templates/packages/     — шаблоны конфигурации

# 3. Создать пакет из своих шаблонов
modxapp create mypackage --template=./my-templates
```

### Использование при создании пакета

Через флаг:

```bash
modxapp create mypackage --template=./my-templates
```

В интерактивном режиме — будет задан вопрос:

```
Custom templates path (leave empty for default):
```

Если ввести путь, он сохранится в `user.config.json` и будет использоваться по умолчанию при следующих запусках — без необходимости указывать `--template` каждый раз.

Без `--template` и без сохранённого пути используются оригинальные шаблоны из пакета.

### Структура папки шаблонов

```
my-templates/
├── components/               — шаблоны для core/components/<name>/
│   ├── bootstrap.php.template
│   ├── composer.json.template
│   ├── phpstan.neon.template
│   ├── docs/
│   ├── lexicon/
│   ├── schema/
│   ├── src/
│   └── elements/
└── packages/                 — шаблоны для packages/<name>/
    ├── config.php.template
    └── elements/
```

### Плейсхолдеры в шаблонах

Файлы с расширением `.template` обрабатываются — в них заменяются плейсхолдеры:

| Плейсхолдер | Значение |
|-------------|----------|
| `{{package_name}}` | Имя в lowercase |
| `{{Package_name}}` | Имя с заглавной |
| `{{short_name}}` | Краткое имя |
| `{{author_name}}` | Имя автора |
| `{{author_email}}` | Email автора |
| `{{php_version}}` | Версия PHP |
| `{{gitlogin}}` | Git username |
| `{{repository}}` | URL репозитория |
| `{{current_year}}` | Текущий год |
| `{{current_date}}` | Текущая дата |

Файлы без `.template` копируются как есть.

!!! tip "Разные шаблоны для разных пакетов"
    Можно создать несколько наборов шаблонов и указывать нужный при создании:
    ```bash
    modxapp create shop --template=./templates/ecommerce
    modxapp create blog --template=./templates/content
    modxapp create api  --template=./templates/headless
    ```
