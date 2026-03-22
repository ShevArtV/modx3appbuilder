# setup — Настройка окружения

Подготавливает локальное окружение для сборки пакетов без установленного MODX. Скачивает ядро MODX 3 и создаёт минимальную конфигурацию с SQLite.

## Использование

```bash
mxbuilder setup
```

## Что происходит

1. **Скачивается ядро MODX 3** из GitHub (последний релиз) в папку `core/` текущей директории. Если ядро уже есть — шаг пропускается
2. **Создаётся `core/config/config.inc.php`** с подключением к SQLite (без MySQL)
3. **Инициализируется SQLite база данных** с минимальными таблицами, необходимыми для работы `modPackageBuilder`
4. **Создаются директории** `core/cache/` и `core/packages/`

## Когда нужен setup

- Вы разрабатываете пакет **локально** и хотите собирать transport.zip без развёрнутого MODX
- На машине **нет MySQL/MariaDB**
- Вы хотите **быстро начать** без установки MODX через Setup

## Когда setup не нужен

- У вас уже есть установленный MODX 3 — сборщик автоматически найдёт `core/config/config.inc.php`
- Вы собираете пакеты на сервере с MODX

## Пример

```bash
mkdir my-project && cd my-project

# Настроить окружение
mxbuilder setup

# Создать пакет
mxbuilder create mypackage

# Собрать transport.zip
mxbuilder build mypackage
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
│   └── mxbuilder.sqlite           — минимальная БД
├── core/components/mypackage/     — исходники пакета (после create)
└── package_builder/packages/      — конфиги сборки (после create)
```

!!! warning "Только для сборки"
    Окружение, созданное через `setup`, предназначено только для сборки transport-пакетов. Команды `elements` и `schema` требуют полноценного MODX с рабочей базой данных.
