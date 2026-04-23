<?php
declare(strict_types=1);

use GuzzleHttp\Psr7\Utils;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Oem\Psr7WhatsappMediaCrypto\Stream\DecryptingStream;
use Oem\Psr7WhatsappMediaCrypto\Stream\EncryptingStream;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'scenario::',
    'sizes::',
    'chunks::',
    'iterations::',
    'workers::',
    'json',
]);

$scenario = $options['scenario'] ?? 'all';
$sizes = parseBytesList($options['sizes'] ?? '10M,100M');
$chunks = parseIntList($options['chunks'] ?? '1,16,257,8192,65536');
$iterations = max(1, (int)($options['iterations'] ?? 1));
$workers = max(1, (int)($options['workers'] ?? 4));
$json = array_key_exists('json', $options);

$crypto = new MediaCrypto(new MediaKeyExpander());
$results = [];

if ($scenario === 'all' || $scenario === 'encrypt') {
    foreach ($sizes as $size) {
        foreach ($chunks as $chunkSize) {
            $results[] = benchmarkEncrypt($crypto, $size, $chunkSize, $iterations);
        }
    }
}

if ($scenario === 'all' || $scenario === 'decrypt') {
    foreach ($sizes as $size) {
        foreach ($chunks as $chunkSize) {
            $results[] = benchmarkDecrypt($crypto, $size, $chunkSize, $iterations);
        }
    }
}

if ($scenario === 'all' || $scenario === 'boundary') {
    foreach ([0, 1, 15, 16, 17, 65535, 65536, 65537] as $size) {
        foreach ($chunks as $chunkSize) {
            $results[] = benchmarkBoundary($crypto, $size, $chunkSize);
        }
    }
}

if ($scenario === 'all' || $scenario === 'parallel') {
    foreach ($sizes as $size) {
        $results[] = benchmarkParallel($size, 8192, $workers, $iterations);
    }
}

if ($json) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

printTable($results);

function benchmarkEncrypt(MediaCrypto $crypto, int $size, int $chunkSize, int $iterations): array
{
    $payload = payload($size);
    $key = random_bytes(32);
    $started = hrtime(true);
    $peakBefore = memory_get_peak_usage(true);
    $bytes = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $stream = new EncryptingStream(
            Utils::streamFor($payload),
            $crypto,
            $key,
            MediaType::VIDEO
        );

        while (!$stream->eof()) {
            $bytes += strlen($stream->read($chunkSize));
        }
    }

    return metricRow(
        scenario: 'encrypt',
        size: $size,
        chunkSize: $chunkSize,
        iterations: $iterations,
        started: $started,
        bytes: $bytes,
        peakBefore: $peakBefore
    );
}

function benchmarkDecrypt(MediaCrypto $crypto, int $size, int $chunkSize, int $iterations): array
{
    $payload = payload($size);
    $key = random_bytes(32);
    $ciphertext = $crypto->encrypt($payload, $key, MediaType::VIDEO->value);
    $started = hrtime(true);
    $peakBefore = memory_get_peak_usage(true);
    $bytes = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $stream = new DecryptingStream(
            Utils::streamFor($ciphertext),
            $crypto,
            $key,
            MediaType::VIDEO
        );

        while (!$stream->eof()) {
            $bytes += strlen($stream->read($chunkSize));
        }
    }

    return metricRow(
        scenario: 'decrypt',
        size: $size,
        chunkSize: $chunkSize,
        iterations: $iterations,
        started: $started,
        bytes: $bytes,
        peakBefore: $peakBefore
    );
}

function benchmarkBoundary(MediaCrypto $crypto, int $size, int $chunkSize): array
{
    $payload = payload($size);
    $key = random_bytes(32);
    $started = hrtime(true);
    $peakBefore = memory_get_peak_usage(true);

    $encrypted = '';
    $stream = new EncryptingStream(
        Utils::streamFor($payload),
        $crypto,
        $key,
        MediaType::IMAGE
    );
    while (!$stream->eof()) {
        $encrypted .= $stream->read($chunkSize);
    }

    $decrypted = '';
    $stream = new DecryptingStream(
        Utils::streamFor($encrypted),
        $crypto,
        $key,
        MediaType::IMAGE
    );
    while (!$stream->eof()) {
        $decrypted .= $stream->read($chunkSize);
    }

    if ($decrypted !== $payload) {
        throw new RuntimeException(sprintf('Boundary round-trip failed for size=%d chunk=%d', $size, $chunkSize));
    }

    return metricRow(
        scenario: 'boundary',
        size: $size,
        chunkSize: $chunkSize,
        iterations: 1,
        started: $started,
        bytes: strlen($decrypted),
        peakBefore: $peakBefore
    );
}

function benchmarkParallel(int $size, int $chunkSize, int $workers, int $iterations): array
{
    $started = hrtime(true);
    $peakBefore = memory_get_peak_usage(true);

    if (!function_exists('pcntl_fork')) {
        return metricRow(
            scenario: 'parallel-unavailable',
            size: $size,
            chunkSize: $chunkSize,
            iterations: $iterations,
            started: $started,
            bytes: 0,
            peakBefore: $peakBefore
        );
    }

    $children = [];
    $bytes = 0;

    for ($worker = 0; $worker < $workers; $worker++) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($pair === false) {
            throw new RuntimeException('Unable to create socket pair for parallel benchmark');
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to fork benchmark worker');
        }

        if ($pid === 0) {
            fclose($pair[0]);

            $crypto = new MediaCrypto(new MediaKeyExpander());
            $payload = payload($size);
            $key = random_bytes(32);
            $processed = 0;

            for ($i = 0; $i < $iterations; $i++) {
                $stream = new EncryptingStream(
                    Utils::streamFor($payload),
                    $crypto,
                    $key,
                    MediaType::AUDIO
                );

                while (!$stream->eof()) {
                    $processed += strlen($stream->read($chunkSize));
                }
            }

            fwrite($pair[1], (string)$processed);
            fclose($pair[1]);
            exit(0);
        }

        fclose($pair[1]);
        $children[] = [$pid, $pair[0]];
    }

    foreach ($children as [$pid, $socket]) {
        $bytes += (int)stream_get_contents($socket);
        fclose($socket);
        pcntl_waitpid($pid, $status);
    }

    return metricRow(
        scenario: sprintf('parallel-%d-workers', $workers),
        size: $size,
        chunkSize: $chunkSize,
        iterations: $iterations,
        started: $started,
        bytes: $bytes,
        peakBefore: $peakBefore
    );
}

function metricRow(
    string $scenario,
    int $size,
    int $chunkSize,
    int $iterations,
    int $started,
    int $bytes,
    int $peakBefore
): array {
    $elapsedNs = hrtime(true) - $started;
    $elapsedSec = $elapsedNs / 1_000_000_000;
    $peakDelta = max(0, memory_get_peak_usage(true) - $peakBefore);
    $throughput = $elapsedSec > 0 ? ($bytes / 1024 / 1024) / $elapsedSec : 0.0;

    return [
        'scenario' => $scenario,
        'size_bytes' => $size,
        'chunk_size' => $chunkSize,
        'iterations' => $iterations,
        'processed_bytes' => $bytes,
        'seconds' => round($elapsedSec, 4),
        'throughput_mb_s' => round($throughput, 2),
        'peak_delta_bytes' => $peakDelta,
    ];
}

function payload(int $size): string
{
    if ($size === 0) {
        return '';
    }

    $pattern = str_repeat('psr7-whatsapp-media-crypto-bench:', 256);
    $buffer = '';

    while (strlen($buffer) < $size) {
        $buffer .= $pattern;
    }

    return substr($buffer, 0, $size);
}

function parseBytesList(string $value): array
{
    return array_map(parseByteString(...), array_filter(array_map('trim', explode(',', $value))));
}

function parseIntList(string $value): array
{
    return array_map(static fn(string $item): int => (int)$item, array_filter(array_map('trim', explode(',', $value))));
}

function parseByteString(string $value): int
{
    if (preg_match('/^(\d+)([KMG])?$/i', $value, $matches) !== 1) {
        throw new InvalidArgumentException(sprintf('Invalid byte size: %s', $value));
    }

    $number = (int)$matches[1];
    $unit = strtoupper($matches[2] ?? '');

    return match ($unit) {
        'K' => $number * 1024,
        'M' => $number * 1024 * 1024,
        'G' => $number * 1024 * 1024 * 1024,
        default => $number,
    };
}

function printTable(array $rows): void
{
    printf(
        "%-20s %-12s %-10s %-10s %-12s %-10s %-12s\n",
        'scenario',
        'size',
        'chunk',
        'iter',
        'processed',
        'sec',
        'mb/s'
    );

    foreach ($rows as $row) {
        printf(
            "%-20s %-12s %-10d %-10d %-12s %-10.4f %-12.2f\n",
            $row['scenario'],
            humanBytes($row['size_bytes']),
            $row['chunk_size'],
            $row['iterations'],
            humanBytes($row['processed_bytes']),
            $row['seconds'],
            $row['throughput_mb_s']
        );
    }
}

function humanBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float)$bytes;
    $unit = 0;

    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }

    return sprintf('%.2f%s', $value, $units[$unit]);
}
