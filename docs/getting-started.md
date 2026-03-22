# Установка

## Системные требования

- **PHP** 8.1+
- **Composer**
- **Расширения PHP**: `pdo_sqlite`, `zip`, `mbstring`
- **MODX Revolution 3** + **MySQL/MariaDB** — только для команд `elements` и `export` (работа с БД)

## Варианты работы

| Вариант | Описание | Установка |
|---------|----------|-----------|
| **Headless** | Локально без MODX и MySQL. Создание, сборка, генерация классов | `modxapp setup` скачает ядро MODX |
| **Локальный MODX** | Полный функционал, все команды | MODX уже установлен |
| **Удалённый сервер** | Код пишется локально, сборка на сервере с MODX | Установить Package Builder на сервере |

В headless-режиме недоступны команды `elements` (добавление элементов в админку) и `export` (извлечение из БД). Все остальные команды работают.

## Через Composer

```bash
composer require shevartv/modx-builder --dev
```

После установки CLI доступен как:

```bash
vendor/bin/modxapp
```

Для глобальной установки (команда `modxapp` доступна из любой директории):

```bash
composer global require shevartv/modx-builder
modxapp
```

## Структура после установки

Package Builder работает с двумя директориями:

```
core/components/mypackage/    — исходники компонента
├── bootstrap.php
├── composer.json
├── docs/
├── lexicon/
├── schema/
├── src/
└── elements/                 — файлы элементов (опционально)

package_builder/packages/mypackage/   — конфигурация сборки
├── config.php
└── elements/
    ├── chunks.php
    ├── snippets.php
    ├── plugins.php
    └── ...
```

## Создание первого пакета

### Интерактивный режим

```bash
modxapp create mypackage --interactive
```

Мастер задаст вопросы:

| Вопрос | Обязательный | По умолчанию |
|--------|:------------:|-------------|
| Имя пакета | да | — |
| Краткое имя (для лексиконов и настроек) | нет | первые 3 символа имени |
| Имя автора | нет | из глобального конфига |
| Email | нет | из глобального конфига |
| Git логин | нет | из глобального конфига |
| Минимальная версия PHP | нет | `8.1` |
| URL репозитория | нет | — |
| Генерировать файлы элементов | нет | `да` |
| Добавить PHP CS Fixer (PSR-12) | нет | `нет` |
| Добавить ESLint для JS | нет | `нет` |

Если вы предварительно выполнили `modxapp config`, значения автора, email и Git логина подставятся автоматически — достаточно нажать Enter.

### Через флаги

```bash
modxapp create mypackage \
    --elements \
    --author="Ivan Petrov" \
    --email=ivan@example.com \
    --gitlogin=ivanpetrov \
    --short-name=my \
    --php-version=8.1
```

## Следующие шаги

1. [Настройте конфигурацию пакета](configuration.md)
2. [Создайте свои шаблоны](commands/templates.md) (опционально)
3. [Опишите элементы](commands/elements.md)
4. [Соберите пакет](commands/build.md)
