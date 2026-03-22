# extract-lexicons — Извлечение лексиконов

Автоматически находит все вызовы `$modx->lexicon()` в PHP-коде и собирает ключи в файл лексикона. Переводы нужно заполнить вручную.

## Использование

```bash
modxapp extract-lexicons <name>
```

## Пример

```bash
modxapp extract-lexicons mypackage
```

## Что делает

1. Сканирует все PHP-файлы в `core/components/<name>/src/`
2. Находит вызовы `$modx->lexicon('key_name')`
3. Генерирует файл `core/components/<name>/lexicon/en/default.inc.php`

## Результат

Из кода:

```php
$modx->lexicon('mypackage_error_not_found');
$modx->lexicon('mypackage_success_saved');
```

Будет сгенерирован:

```php
<?php
$_lang['mypackage_error_not_found'] = 'mypackage_error_not_found';
$_lang['mypackage_success_saved'] = 'mypackage_success_saved';
```

!!! note "Значения по умолчанию"
    Ключи лексикона создаются со значениями, совпадающими с ключами. Перевод нужно заполнить вручную.

## Именование ключей

Рекомендуется использовать `snake_case` с префиксом пакета:

```
mypackage_error_not_found
mypackage_btn_save
mypackage_title_main
```
