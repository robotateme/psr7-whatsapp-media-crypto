<?php
declare(strict_types=1);

namespace Oem\Psr7WhatsappMediaCrypto\Stream;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Oem\Psr7WhatsappMediaCrypto\Crypto\ExpandedKeys;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Override;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * @psalm-suppress UnusedClass
 */
final class DecryptingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private const int BLOCK_SIZE = 16;
    private const int MAC_LENGTH = 10;
    private const int READ_CHUNK_SIZE = 8192;

    private bool $initialized = false;
    private bool $finalized = false;
    private string $pending = '';
    private string $currentIv = '';
    private int $plainSize = 0;
    private ExpandedKeys $keys;
    private \HashContext $macContext;
    private StreamInterface $plainStream;

    public function __construct(
        protected StreamInterface    $stream,
        private readonly MediaCrypto $crypto,
        private readonly string      $key,
        private readonly MediaType   $type
    ) {
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $resource = fopen('php://temp', 'w+b');
        if ($resource === false) {
            throw new RuntimeException('Unable to allocate temporary stream');
        }

        $this->plainStream = Utils::streamFor($resource);
        $this->keys = $this->crypto->expandKeys($this->key, $this->type->value);
        $this->currentIv = $this->keys->iv;
        $this->macContext = hash_init('sha256', HASH_HMAC, $this->keys->macKey);
        hash_update($this->macContext, $this->keys->iv);
        $this->initialized = true;
    }

    private function processAll(): void
    {
        $this->initialize();

        while (!$this->finalized) {
            $chunk = $this->stream->read(self::READ_CHUNK_SIZE);
            if ($chunk === '') {
                if ($this->stream->eof()) {
                    $this->finalizeStream();
                }
                break;
            }

            $this->pending .= $chunk;
            $this->flushCiphertext();
        }
    }

    private function flushCiphertext(): void
    {
        $processableLength = max(0, strlen($this->pending) - self::MAC_LENGTH);
        $processableLength -= $processableLength % self::BLOCK_SIZE;

        if ($processableLength === 0) {
            return;
        }

        $cipherChunk = substr($this->pending, 0, $processableLength);
        $this->pending = substr($this->pending, $processableLength);
        $this->decryptChunk($cipherChunk);
    }

    private function decryptChunk(string $cipherChunk): void
    {
        if ($cipherChunk === '') {
            return;
        }

        hash_update($this->macContext, $cipherChunk);

        $plainChunk = openssl_decrypt(
            $cipherChunk,
            'aes-256-cbc',
            $this->keys->cipherKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $this->currentIv
        );

        if ($plainChunk === false) {
            throw new RuntimeException('Unable to decrypt data');
        }

        $this->currentIv = substr($cipherChunk, -self::BLOCK_SIZE);
        $this->plainStream->write($plainChunk);
    }

    private function finalizeStream(): void
    {
        if (strlen($this->pending) < self::MAC_LENGTH) {
            throw new RuntimeException('Ciphertext too short');
        }

        $cipherTail = substr($this->pending, 0, -self::MAC_LENGTH);
        $mac = substr($this->pending, -self::MAC_LENGTH);

        if (strlen($cipherTail) % self::BLOCK_SIZE !== 0) {
            throw new RuntimeException('Invalid ciphertext length');
        }

        $this->decryptChunk($cipherTail);

        $calcMac = substr(hash_final($this->macContext, true), 0, self::MAC_LENGTH);
        if (!hash_equals($mac, $calcMac)) {
            throw new RuntimeException('Invalid MAC');
        }

        $writtenSize = $this->plainStream->getSize();
        if ($writtenSize === null || $writtenSize < self::BLOCK_SIZE) {
            throw new RuntimeException('Invalid plaintext length');
        }

        $this->plainStream->seek($writtenSize - self::BLOCK_SIZE);
        $lastBlock = $this->plainStream->read(self::BLOCK_SIZE);
        if (strlen($lastBlock) !== self::BLOCK_SIZE) {
            throw new RuntimeException('Invalid padding block');
        }

        $paddingLength = ord($lastBlock[self::BLOCK_SIZE - 1]);
        if ($paddingLength < 1 || $paddingLength > self::BLOCK_SIZE) {
            throw new RuntimeException('Invalid padding');
        }

        $padding = substr($lastBlock, -$paddingLength);
        if ($padding !== str_repeat(chr($paddingLength), $paddingLength)) {
            throw new RuntimeException('Invalid padding');
        }

        $this->plainSize = $writtenSize - $paddingLength;
        $this->plainStream->rewind();
        $this->pending = '';
        $this->finalized = true;
    }

    #[Override]
    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $this->processAll();

        $remaining = $this->plainSize - $this->plainStream->tell();
        if ($remaining <= 0) {
            return '';
        }

        return $this->plainStream->read(min($length, $remaining));
    }

    #[Override]
    public function getContents(): string
    {
        $this->processAll();

        $remaining = $this->plainSize - $this->plainStream->tell();
        if ($remaining <= 0) {
            return '';
        }

        return $this->plainStream->read($remaining);
    }

    #[Override]
    public function eof(): bool
    {
        $this->processAll();
        return $this->plainStream->tell() >= $this->plainSize;
    }

    #[Override]
    public function tell(): int
    {
        return $this->initialized ? $this->plainStream->tell() : 0;
    }

    #[Override]
    public function rewind(): void
    {
        throw new RuntimeException('Stream is not seekable');
    }

    #[Override]
    public function isSeekable(): bool
    {
        return false;
    }

    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('Stream is not seekable');
    }

    #[Override]
    public function getSize(): ?int
    {
        return $this->finalized ? $this->plainSize : null;
    }

    #[Override]
    public function close(): void
    {
        if ($this->initialized) {
            $this->plainStream->close();
        }

        $this->pending = '';
        $this->plainSize = 0;
        $this->finalized = true;
        $this->initialized = false;
        $this->stream->close();
    }

    #[Override]
    public function detach(): null
    {
        $this->close();
        return null;
    }

    #[Override]
    public function __toString(): string
    {
        try {
            if ($this->tell() !== 0) {
                return '';
            }

            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }
}
