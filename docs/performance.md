# Performance and Load Scenarios

The repository now includes both PHPUnit-based load smoke tests and a standalone benchmark script.

## Included Scenarios

### PHPUnit performance suite

Run:

```bash
composer test:performance
```

Covered cases:

- boundary payload sizes: `0`, `1`, `15`, `16`, `17`, `65535`, `65536`, `65537`
- chunked reads across boundary-sensitive sizes
- large encrypt stream smoke test on `10MB`
- large decrypt round-trip smoke test on `5MB + 13 bytes`

These tests are intended to validate behavior under load-like conditions without making default CI flaky.

### Benchmark script

Run:

```bash
composer bench:stream
```

Custom examples:

```bash
php bench/stream_benchmark.php --scenario=encrypt --sizes=10M,100M --chunks=8192,65536
php bench/stream_benchmark.php --scenario=decrypt --sizes=10M --chunks=257,8192 --iterations=3
php bench/stream_benchmark.php --scenario=parallel --sizes=10M --workers=4 --iterations=2
php bench/stream_benchmark.php --scenario=boundary --chunks=1,16,257 --json
```

## Benchmark Scenarios

- `encrypt`: measures chunked encryption throughput
- `decrypt`: measures chunked decrypt throughput
- `boundary`: validates payload-size edge cases
- `parallel`: forks multiple worker processes via `pcntl_fork` and runs parallel encrypt workloads

## What to Watch

- elapsed time
- throughput in MB/s
- peak memory delta
- chunk-size sensitivity
- behavior on large payloads

## Recommended Workflow

1. Run `composer test` for normal correctness checks.
2. Run `composer test:performance` before merging stream-related changes.
3. Run `composer bench:stream` when changing chunk sizes, buffering logic, MAC handling, or temporary stream behavior.
