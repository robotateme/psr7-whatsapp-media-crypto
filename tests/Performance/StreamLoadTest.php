<?php
declare(strict_types=1);

namespace Oem\Psr7WhatsappMediaCrypto\Test\Performance;

use GuzzleHttp\Psr7\Utils;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Oem\Psr7WhatsappMediaCrypto\Stream\DecryptingStream;
use Oem\Psr7WhatsappMediaCrypto\Stream\EncryptingStream;
use Oem\Psr7WhatsappMediaCrypto\Test\Support\TrackingStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Random\RandomException;

final class StreamLoadTest extends TestCase
{
    private MediaCrypto $crypto;

    protected function setUp(): void
    {
        $this->crypto = new MediaCrypto(new MediaKeyExpander());
    }

    /**
     * @throws RandomException
     */
    #[DataProvider('boundaryCases')]
    public function testBoundaryPayloadSizesRoundTrip(int $size, int $chunkSize): void
    {
        $data = $this->payload($size);
        $key = random_bytes(32);

        $encStream = new EncryptingStream(
            Utils::streamFor($data),
            $this->crypto,
            $key,
            MediaType::IMAGE
        );

        $encrypted = $this->readAll($encStream, $chunkSize);

        $decStream = new DecryptingStream(
            Utils::streamFor($encrypted),
            $this->crypto,
            $key,
            MediaType::IMAGE
        );

        $decrypted = $this->readAll($decStream, $chunkSize);

        $this->assertSame($data, $decrypted);
    }

    /**
     * @throws RandomException
     */
    public function testLargeEncryptingStreamDoesNotReadWholeSourceAtOnce(): void
    {
        $data = $this->payload(10 * 1024 * 1024);
        $key = random_bytes(32);
        $source = new TrackingStream(Utils::streamFor($data));

        $stream = new EncryptingStream(
            $source,
            $this->crypto,
            $key,
            MediaType::VIDEO
        );

        $chunk = $stream->read(32);

        $this->assertNotSame('', $chunk);
        $this->assertGreaterThan(0, $source->bytesRead);
        $this->assertLessThan(strlen($data), $source->bytesRead);
        $this->assertGreaterThanOrEqual(1, $source->readCalls);
    }

    /**
     * @throws RandomException
     */
    public function testLargeDecryptingStreamRoundTripUsingChunkedReads(): void
    {
        $data = $this->payload((5 * 1024 * 1024) + 13);
        $key = random_bytes(32);

        $encrypted = $this->crypto->encrypt($data, $key, MediaType::DOCUMENT->value);

        $stream = new DecryptingStream(
            Utils::streamFor($encrypted),
            $this->crypto,
            $key,
            MediaType::DOCUMENT
        );

        $hash = hash_init('sha256');
        $read = 0;

        while (!$stream->eof()) {
            $chunk = $stream->read(257);
            $read += strlen($chunk);
            hash_update($hash, $chunk);
        }

        $this->assertSame(strlen($data), $read);
        $this->assertSame(hash('sha256', $data), hash_final($hash));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function boundaryCases(): array
    {
        return [
            'empty-1' => [0, 1],
            '1-byte-1' => [1, 1],
            '15-bytes-16' => [15, 16],
            '16-bytes-16' => [16, 16],
            '17-bytes-16' => [17, 16],
            '65535-bytes-257' => [65535, 257],
            '65536-bytes-8192' => [65536, 8192],
            '65537-bytes-65536' => [65537, 65536],
        ];
    }

    private function payload(int $size): string
    {
        if ($size === 0) {
            return '';
        }

        $seed = str_repeat('psr7-whatsapp-media-crypto:', 64);
        $result = '';

        while (strlen($result) < $size) {
            $result .= $seed;
        }

        return substr($result, 0, $size);
    }

    private function readAll(StreamInterface $stream, int $chunkSize): string
    {
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read($chunkSize);
        }

        return $buffer;
    }
}
