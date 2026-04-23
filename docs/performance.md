# Сценарии производительности и нагрузки

В репозитории теперь есть как нагрузочные smoke-тесты на базе PHPUnit, так и отдельный benchmark-скрипт.

## Доступные сценарии

### Набор performance-тестов для PHPUnit

Запуск:

```bash
composer test:performance
```

Покрываемые случаи:

- граничные размеры payload: `0`, `1`, `15`, `16`, `17`, `65535`, `65536`, `65537`
- chunked reads на размерах, чувствительных к границам блоков
- большой smoke-тест для encrypt stream на `10MB`
- большой round-trip smoke test для decrypt на `5MB + 13 bytes`

Эти тесты нужны для проверки поведения в условиях, близких к нагрузочным, но без превращения стандартного CI в нестабильный процесс.

### Benchmark-скрипт

Запуск:

```bash
composer bench:stream
```

Примеры запуска:

```bash
php bench/stream_benchmark.php --scenario=encrypt --sizes=10M,100M --chunks=8192,65536
php bench/stream_benchmark.php --scenario=decrypt --sizes=10M --chunks=257,8192 --iterations=3
php bench/stream_benchmark.php --scenario=parallel --sizes=10M --workers=4 --iterations=2
php bench/stream_benchmark.php --scenario=boundary --chunks=1,16,257 --json
```

## Сценарии benchmark

- `encrypt`: измеряет пропускную способность chunked encryption
- `decrypt`: измеряет пропускную способность chunked decrypt
- `boundary`: проверяет граничные случаи по размеру payload
- `parallel`: форкает несколько worker-процессов через `pcntl_fork` и запускает параллельные encrypt-нагрузки

## Что стоит отслеживать

- затраченное время
- пропускную способность в MB/s
- прирост peak memory
- чувствительность к размеру chunk
- поведение на больших payload

## Рекомендуемый порядок работы

1. Запускайте `composer test` для обычных проверок корректности.
2. Запускайте `composer test:performance` перед слиянием изменений, связанных со stream-логикой.
3. Запускайте `composer bench:stream`, когда меняете размеры chunk, логику буферизации, обработку MAC или поведение временного потока.
