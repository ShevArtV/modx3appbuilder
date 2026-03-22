# setup — Настройка окружения

Подготавливает локальное окружение для сборки пакетов без необходимости поднимать сервер и устанавливать MODX. Скачивает ядро MODX 3 и создаёт минимальную конфигурацию с SQLite.

## Использование

```bash
modxapp setup
```

## Что происходит

1. **Скачивается ядро MODX 3** из GitHub (последний релиз) в папку `core/` текущей директории. Если ядро уже есть — шаг пропускается
2. **Создаётся `core/config/config.inc.php`** с подключением к SQLite (без MySQL)
3. **Инициализируется SQLite база данных** с минимальными таблицами, необходимыми для работы `modPackageBuilder`
4. **Создаются директории** `core/cache/` и `core/packages/`

## Два режима работы

Package Builder работает в двух режимах:

### Headless (после `modxapp setup`)

Локальная разработка без сервера и MySQL. Ядро MODX + SQLite.

| Команда | Доступна |
|---------|----------|
| `create` | да |
| `build` | да |
| `schema` | да (генерация классов, без создания таблиц) |
| `extract-lexicons` | да |
| `extract-settings` | да |
| `elements` | нет — нужна БД MODX с таблицами |
| `export` | нет — нужна БД MODX с элементами |

### Полный MODX (установленный сайт)

Работают все команды, включая `elements` (добавление элементов в админку) и `export` (извлечение из БД в файлы). Команда `build --install` устанавливает пакет на сайт.

## Когда нужен setup

- Вы разрабатываете пакет **локально** и хотите собирать transport.zip без развёрнутого MODX
- На машине **нет MySQL/MariaDB**
- Вы хотите **быстро начать** без установки MODX через Setup

## Когда setup не нужен

- У вас уже есть установленный MODX 3 — сборщик автоматически найдёт `core/config/config.inc.php`
- Вы собираете пакеты на сервере с MODX

!!! note "Сборка на удалённом сервере"
    Если MODX установлен на удалённом сервере и вы хотите собирать пакеты там — установите Package Builder на сервере (`composer global require shevartv/modx-builder`). Команда `setup` в этом случае не нужна.

## Пример

```bash
mkdir my-project && cd my-project

# Настроить окружение
modxapp setup

# Создать пакет
modxapp create mypackage

# Собрать transport.zip
modxapp build mypackage
```

## Структура после setup

```
my-project/
├── core/                          — ядро MODX 3 (скачивается)
│   ├── config/
│   │   └── config.inc.php         — конфиг с SQLite
│   ├── cache/
│   ├── packages/                  — сюда попадают собранные пакеты
│   ├── src/Revolution/            — классы MODX
│   └── modxapp.sqlite           — минимальная БД
├── core/components/mypackage/     — исходники пакета (после create)
└── package_builder/packages/      — конфиги сборки (после create)
```
