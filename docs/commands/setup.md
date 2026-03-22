# setup — Настройка окружения

Подготавливает локальное окружение для сборки пакетов без необходимости поднимать сервер и устанавливать MODX. Скачивает ядро MODX 3 и настраивает минимальное окружение.

## Использование

```bash
modxapp setup
```

## Что происходит

1. **Скачивается ядро MODX 3** из GitHub (последний релиз) в папку `core/` текущей директории. Если ядро уже есть — шаг пропускается
2. **Устанавливаются зависимости** MODX через Composer (xPDO и другие библиотеки)
3. **Создаётся конфигурация** для работы standalone-сборщика
4. **Создаются директории** `core/cache/` и `core/packages/`

## Два режима сборки

Package Builder автоматически выбирает режим сборки:

### Headless (после `modxapp setup`)

Локальная разработка без сервера и MySQL. Сборка через собственный standalone-сборщик, который генерирует transport.zip без обращения к БД.

| Команда | Доступна |
|---------|----------|
| `create` | да |
| `build` | да (standalone-сборщик) |
| `extract-lexicons` | да |
| `extract-settings` | да |
| `templates` | да |
| `elements` | нет — нужна БД MODX |
| `export` | нет — нужна БД MODX |

### Полный MODX (установленный сайт)

Работают все команды. Сборка через стандартный `modPackageBuilder` MODX. Команды `elements`, `export`, `build --install` полностью доступны.

## Когда нужен setup

- Вы разрабатываете пакет **локально** и хотите собирать transport.zip без развёрнутого MODX
- На машине **нет MySQL/MariaDB**
- Вы хотите **быстро начать** без установки MODX

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
│   │   └── config.inc.php         — конфигурация окружения
│   ├── vendor/                    — зависимости MODX (xPDO)
│   ├── cache/
│   ├── packages/                  — сюда попадают собранные пакеты
│   └── src/Revolution/            — классы MODX
├── core/components/mypackage/     — исходники пакета (после create)
└── package_builder/packages/      — конфиги сборки (после create)
```

Подробнее о сценариях: [Сценарии работы](../workflows.md)
