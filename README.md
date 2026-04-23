# PSR-7 WhatsApp Media Crypto

[![GitHub Actions](https://github.com/robotateme/psr7-whatsapp-media-crypto/actions/workflows/php.yml/badge.svg?branch=master)](https://github.com/robotateme/psr7-whatsapp-media-crypto/actions/workflows/php.yml)

Утилиты для шифрования медиафайлов WhatsApp, построенные на PSR-7 потоках.

## Документация

- [Обзор](docs/overview.md)
- [Использование](docs/usage.md)
- [Заметки по устройству](docs/design.md)
- [CI](docs/ci.md)
- [Сценарии производительности и нагрузки](docs/performance.md)

## Быстрый старт

```bash
composer install
composer test
```

Дополнительные команды для проверки производительности:

```bash
composer test:performance
composer bench:stream
```
