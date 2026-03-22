# init — Инициализация проекта

Создаёт локальный конфиг `modxapp.json` в текущей директории. Значения по умолчанию берутся из глобального конфига.

## Использование

```bash
modxapp init
```

## Что происходит

1. Загружаются значения из глобального конфига (если настроен через `modxapp config`)
2. Для каждого параметра задаётся вопрос — можно принять значение по умолчанию (Enter) или ввести своё
3. Создаётся файл `modxapp.json` в текущей директории

Если `modxapp.json` уже существует, будет запрошено подтверждение на перезапись.

## Пример

```bash
$ modxapp init

=== Package Builder Project Init ===
Configure settings for this project. Press Enter to use defaults.

Author name [Shevchenko Arthur]:
Author email [shev.art.v@ya.ru]:
Git login [shevartv]:
Minimum PHP version [8.1]:
Repository URL [https://github.com/]:
Templates path (leave empty for default):
Generate elements files? [Y/n]:
Add PHP CS Fixer? [y/N]:
Add ESLint? [y/N]:

SUCCESS: modxapp.json created
```

## Формат modxapp.json

```json
{
    "author": "Shevchenko Arthur",
    "email": "shev.art.v@ya.ru",
    "gitlogin": "shevartv",
    "phpVersion": "8.1",
    "repository": "https://github.com/",
    "template": "",
    "generateElements": true,
    "phpCsFixer": false,
    "eslint": false
}
```

## Когда нужен init

- Если вы хотите настроить проект с параметрами, отличающимися от глобальных
- Если хотите задать путь к кастомным шаблонам для проекта
- Если работаете в команде — `modxapp.json` можно закоммитить в репозиторий, и все участники будут использовать одинаковые настройки

!!! tip "init не обязателен"
    Если вы не вызывали `init`, первый `modxapp create` автоматически создаст `modxapp.json` из глобального конфига.
