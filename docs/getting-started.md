# Установка

## Системные требования

- **PHP** 8.1+
- **Composer**
- **Расширения PHP**: `pdo_sqlite`, `zip`, `mbstring`
- **MODX Revolution 3** + **MySQL/MariaDB** — только для команд `elements` и `export` (работа с БД)

## Варианты работы

| Вариант | Описание | Установка |
|---------|----------|-----------|
| **Headless** | Локально без MODX и MySQL. Создание, сборка, генерация классов | `mxbuilder setup` скачает ядро MODX |
| **Локальный MODX** | Полный функционал, все команды | MODX уже установлен |
| **Удалённый сервер** | Код пишется локально, сборка на сервере с MODX | Установить Package Builder на сервере |

В headless-режиме недоступны команды `elements` (добавление элементов в админку) и `export` (извлечение из БД). Все остальные команды работают.

## Через Composer

```bash
composer require shevartv/modx-builder --dev
```

После установки CLI доступен как:

```bash
vendor/bin/mxbuilder
```

Для глобальной установки (команда `mxbuilder` доступна из любой директории):

```bash
composer global require shevartv/modx-builder
mxbuilder
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
mxbuilder create mypackage --interactive
```

Мастер задаст вопросы:

- Имя автора
- Email
- Git логин
- Краткое имя компонента (для лексиконов)
- Минимальная версия PHP
- URL репозитория
- Генерировать ли файлы элементов
- Добавить PHP CS Fixer (PSR-12)
- Добавить ESLint для JS

Ответы сохраняются в `user.config.json` и используются как значения по умолчанию при следующем создании.

### Через флаги

```bash
mxbuilder create mypackage \
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
