# Шифрование пакетов

Package Builder поддерживает шифрование платных пакетов через modstore.pro API (AES-256-CBC).

## Использование

```bash
modxapp build mypackage --encrypt
```

Или через web:

```
build_web.php?package=mypackage&encrypt
```

## Как это работает

1. Пакет собирается обычным способом
2. PHP-файлы шифруются с помощью `EncryptedVehicle`
3. Шифрование использует алгоритм AES-256-CBC
4. Ключ дешифровки привязан к домену через modstore.pro API
5. При установке пакет автоматически запрашивает ключ

## Настройка в конфиге

Для включения шифрования добавьте в `config.php`:

```php
'encrypt' => true,
```

Флаг `--encrypt` в CLI или параметр `encrypt` в web-запросе активирует шифрование при сборке.

## Требования

- Аккаунт на modstore.pro
- API-ключ modstore.pro
- Пакет должен быть зарегистрирован на modstore.pro
