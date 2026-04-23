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

## Результаты нагрузочного тестирования

Ниже приведены ориентировочные результаты локального прогона на этой машине от `2026-04-23` с `PHP 8.4.1`.

Использованные команды:

```bash
php bench/stream_benchmark.php --scenario=encrypt --sizes=10M --chunks=8192,65536 --iterations=3
php bench/stream_benchmark.php --scenario=decrypt --sizes=10M --chunks=8192,65536 --iterations=3
php bench/stream_benchmark.php --scenario=parallel --sizes=10M --workers=4 --iterations=2
```

Краткая сводка:

- `encrypt`, `10MB`, `chunk=8192`, `3` итерации: `108.58 MB/s`, `0.2763 s`
- `encrypt`, `10MB`, `chunk=65536`, `3` итерации: `60.89 MB/s`, `0.4927 s`
- `decrypt`, `10MB`, `chunk=8192`, `3` итерации: `75.22 MB/s`, `0.3988 s`
- `decrypt`, `10MB`, `chunk=65536`, `3` итерации: `70.44 MB/s`, `0.4259 s`
- `parallel`, `4` worker, `10MB`, `2` итерации: `161.57 MB/s`, `0.4951 s`

По метрике `peak_delta_bytes` дополнительный пик памяти в этих прогонах не был зафиксирован, что согласуется с текущей потоковой реализацией `EncryptingStream`.
