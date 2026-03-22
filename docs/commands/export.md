# export — Извлечение элементов из MODX

Извлекает элементы компонента из базы данных MODX в PHP-файлы и файлы контента. Обратная операция к команде `elements`.

## Использование

```bash
mxbuilder export <name> [--force]
```

## Опции

| Опция | Описание |
|-------|----------|
| `--force` | Перезаписать файлы без подтверждения |

## Пример

```bash
mxbuilder export mypackage
```

Результат:

```
  chunks: 3
  snippets: 2
  plugins: 1
  settings: 5
SUCCESS: Exported 11 elements for mypackage
```

## Что извлекается

| Элемент | Поиск в БД | Контент | Описание |
|---------|-----------|---------|----------|
| Chunks | По категории | `elements/chunks/*.tpl` | Чанки с контентом в файлах |
| Snippets | По категории | `elements/snippets/*.php` | Сниппеты с кодом в файлах |
| Plugins | По категории + события | `elements/plugins/*.php` | Плагины с привязкой к событиям |
| Templates | По категории | `elements/templates/*.tpl` | Шаблоны с контентом в файлах |
| TV | По категории | — | TV-параметры (без файлов контента) |
| Settings | По namespace | — | Системные настройки |
| Menus | По namespace | — | Пункты меню |
| Events | По имени пакета | — | Кастомные события |

## Как работает поиск

- **Chunks, snippets, plugins, templates, TV** — ищутся по категории, указанной в `config.php` → `elements.category`
- **Settings, menus** — ищутся по namespace = `name_lower` из `config.php`
- **Events** — ищутся кастомные события (service=6), содержащие имя пакета

## Структура после export

```
core/components/mypackage/
└── elements/
    ├── chunks/
    │   ├── mychunk.tpl
    │   └── another.tpl
    ├── snippets/
    │   └── mysnippet.php
    ├── plugins/
    │   └── myplugin.php
    └── templates/
        └── mytemplate.tpl

package_builder/packages/mypackage/
└── elements/
    ├── chunks.php
    ├── snippets.php
    ├── plugins.php
    ├── templates.php
    ├── tvs.php
    ├── settings.php
    ├── menus.php
    └── events.php
```

## Когда использовать

- Элементы создавались через админку и нужно перенести их в файлы для сборки
- Миграция существующего компонента на Package Builder
- Бэкап элементов из БД в файлы

!!! warning "Требует полный MODX"
    Команда `export` работает только с установленным MODX и подключением к базе данных. В headless-режиме недоступна.
