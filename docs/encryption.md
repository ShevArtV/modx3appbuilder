# Шифрование пакетов

Package Builder поддерживает шифрование платных пакетов через modstore.pro API (AES-256-CBC).

## Настройка

В `config.php` пакета:

```php
'encrypt' => [
    'enable' => true,
    'login' => 'your-modstore-login',
    'api_key' => 'your-modstore-api-key',
],
```

## Использование

```bash
modxapp build mypackage --encrypt
```

Или через web:

```
build_web.php?package=mypackage&encrypt
```

Если `encrypt.enable` установлен в `true` в конфиге — флаг `--encrypt` не нужен.

## Как это работает

1. Пакет собирается обычным способом
2. PHP-файлы шифруются с помощью `EncryptedVehicle`
3. Шифрование использует алгоритм AES-256-CBC
4. Ключ дешифровки привязан к домену через modstore.pro API
5. При установке пакет автоматически запрашивает ключ

## Требования

- Аккаунт на modstore.pro
- Логин и API-ключ modstore.pro (указываются в конфиге пакета)
- Пакет должен быть зарегистрирован на modstore.pro

!!! warning "Безопасность"
    Не коммитьте `config.php` с логином и API-ключом в публичные репозитории. Добавьте `package_builder/packages/*/config.php` в `.gitignore` или используйте переменные окружения.
