# Package Builder

CLI-инструмент для быстрого старта разработки пакетов под MODX Revolution 3.

## Возможности

- Создание структуры пакета за одну команду
- Сборка transport.zip без установленного MODX (headless-режим с SQLite)
- Добавление элементов (сниппеты, чанки, плагины, TV, меню) без входа в админку
- Извлечение лексиконов и настроек из PHP-кода
- PHPStan, PHP CS Fixer, ESLint — настраиваются автоматически
- Кастомные шаблоны для разных типов пакетов
- Шифрование платных пакетов через modstore.pro

## Установка

```bash
composer global require shevartv/modx-builder
```

## Быстрый старт

```bash
modxapp setup                    # скачать ядро MODX (если нет)
modxapp config                   # настроить данные автора
modxapp create mypackage         # создать пакет
modxapp build mypackage          # собрать transport.zip
```

## Документация

[shevartv.github.io/modx-builder](https://shevartv.github.io/modx-builder/)

## Требования

- PHP 8.1+
- Composer

## Лицензия

MIT
