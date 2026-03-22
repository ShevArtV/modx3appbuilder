# Инструменты разработки

Package Builder может автоматически настроить инструменты статического анализа и линтинга при создании пакета.

## PHPStan

Всегда включён. Добавляется в `composer.json` как dev-зависимость.

### Конфигурация

Файл `phpstan.neon` создаётся автоматически:

```yaml
parameters:
    level: 5
    paths:
        - src
        - elements
    excludePaths:
        analyse:
            - src/Model
            - vendor
```

### Запуск

```bash
cd core/components/mypackage/
composer install
composer analyse
```

### Уровни анализа

PHPStan использует уровень **5** из 9 — строгая проверка типов без излишеств:

- Уровни 0–4: базовые проверки
- **Уровень 5**: проверка типов аргументов и возвращаемых значений
- Уровни 6–9: максимально строгие проверки

## PHP CS Fixer

Опциональный. Включается флагом `--php-cs-fixer` при создании или ключом `phpCsFixer: true` в конфиге.

### Конфигурация

Файл `.php-cs-fixer.dist.php`:

```php
<?php
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/elements')
    ->exclude('Model');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(false);
```

### Запуск

```bash
# Проверить код
composer cs-check

# Автоисправить
composer cs-fix
```

### Правила

| Правило | Описание |
|---------|----------|
| `@PSR12` | Стандарт PSR-12 |
| `array_syntax` | Короткий синтаксис массивов `[]` |
| `no_unused_imports` | Удаление неиспользуемых `use` |
| `ordered_imports` | Сортировка импортов по алфавиту |
| `single_quote` | Одинарные кавычки для строк |
| `trailing_comma_in_multiline` | Запятая после последнего элемента |

## ESLint

Опциональный. Включается флагом `--eslint` при создании или ключом `eslint: true` в конфиге.

### Конфигурация

Файл `eslint.config.js` (flat config, ESLint 9+):

```javascript
import js from '@eslint/js';
import globals from 'globals';

export default [
  {
    ignores: ['node_modules/**', 'lib/**'],
  },
  js.configs.recommended,
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
      },
    },
    rules: {
      'indent': ['error', 2],
      'quotes': ['error', 'single', { allowTemplateLiterals: true }],
      'semi': ['error', 'always'],
      'no-useless-escape': 'off',
    }
  },
];
```

### Установка и запуск

```bash
npm install
npm run lint          # проверить
npm run lint:fix      # автоисправить
```

## Свои конфиги инструментов

По умолчанию при `create` используются встроенные конфиги. Если вы хотите, чтобы все проекты использовали одинаковые настройки инструментов — создайте папку с вашими конфигами:

```
~/my-tool-configs/
├── phpstan.neon
├── .php-cs-fixer.dist.php
└── eslint.config.js
```

Укажите путь к папке в глобальном конфиге:

```bash
mxbuilder config
# → Path to custom tool configs: ~/my-tool-configs
```

Или в `mxbuilder.json` (локальный конфиг проекта):

```json
{
    "toolsConfigPath": "/home/user/my-tool-configs"
}
```

При `mxbuilder create` файлы из этой папки заменят встроенные шаблоны. Не обязательно класть все три файла — если в папке есть только `phpstan.neon`, заменён будет только он, остальные останутся встроенными.

## Сборка пакета

Все файлы инструментов (`phpstan.neon`, `.php-cs-fixer.dist.php`, `eslint.config.js`, `package.json`, `node_modules/`) автоматически исключаются из transport-пакета через [.packignore](packignore.md).
