<?php
declare(strict_types=1);

namespace Oem\Psr7WhatsappMediaCrypto\Stream;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
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
final class EncryptingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private const int BLOCK_SIZE = 16;
    private const int MAC_LENGTH = 10;
    private const int READ_CHUNK_SIZE = 8192;

    private bool $initialized = false;
    private bool $finalized = false;
    private string $plainBuffer = '';
    private string $outputBuffer = '';
    private string $currentIv = '';
    private int $position = 0;
    private ?int $knownSize = null;
    private ExpandedKeys $keys;
    private \HashContext $macContext;

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

        $this->keys = $this->crypto->expandKeys($this->key, $this->type->value);
        $this->currentIv = $this->keys->iv;
        $this->macContext = hash_init('sha256', HASH_HMAC, $this->keys->macKey);
        hash_update($this->macContext, $this->keys->iv);

        $sourceSize = $this->stream->getSize();
        if ($sourceSize !== null) {
            $paddingLength = self::BLOCK_SIZE - ($sourceSize % self::BLOCK_SIZE);
            $this->knownSize = $sourceSize + $paddingLength + self::MAC_LENGTH;
        }

        $this->initialized = true;
    }

    private function pump(int $targetLength): void
    {
        $this->initialize();

        while (!$this->finalized && strlen($this->outputBuffer) < $targetLength) {
            $chunk = $this->stream->read(self::READ_CHUNK_SIZE);
            if ($chunk === '') {
                if ($this->stream->eof()) {
                    $this->finalizeStream();
                }
                break;
            }

            $this->plainBuffer .= $chunk;
            $this->flushFullBlocks();
        }
    }

    private function flushFullBlocks(): void
    {
        $processableLength = strlen($this->plainBuffer) - (strlen($this->plainBuffer) % self::BLOCK_SIZE);
        if ($processableLength === 0) {
            return;
        }

        $plainChunk = substr($this->plainBuffer, 0, $processableLength);
        $this->plainBuffer = substr($this->plainBuffer, $processableLength);

        $cipherChunk = openssl_encrypt(
            $plainChunk,
            'aes-256-cbc',
            $this->keys->cipherKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $this->currentIv
        );

        if ($cipherChunk === false) {
            throw new RuntimeException('Unable to encrypt data');
        }

        $this->currentIv = substr($cipherChunk, -self::BLOCK_SIZE);
        hash_update($this->macContext, $cipherChunk);
        $this->outputBuffer .= $cipherChunk;
    }

    private function finalizeStream(): void
    {
        $cipherTail = openssl_encrypt(
            $this->plainBuffer,
            'aes-256-cbc',
            $this->keys->cipherKey,
            OPENSSL_RAW_DATA,
            $this->currentIv
        );

        if ($cipherTail === false) {
            throw new RuntimeException('Unable to encrypt data');
        }

        hash_update($this->macContext, $cipherTail);
        $mac = substr(hash_final($this->macContext, true), 0, self::MAC_LENGTH);

        $this->plainBuffer = '';
        $this->outputBuffer .= $cipherTail . $mac;
        $this->finalized = true;
    }

    #[Override]
    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $this->pump($length);

        $chunk = substr($this->outputBuffer, 0, $length);
        $this->outputBuffer = substr($this->outputBuffer, strlen($chunk));
        $this->position += strlen($chunk);

        return $chunk;
    }

    #[Override]
    public function getContents(): string
    {
        $this->pump(PHP_INT_MAX);

        $remaining = $this->outputBuffer;
        $this->position += strlen($remaining);
        $this->outputBuffer = '';

        return $remaining;
    }

    #[Override]
    public function eof(): bool
    {
        $this->pump(1);
        return $this->finalized && $this->outputBuffer === '';
    }

    #[Override]
    public function tell(): int
    {
        return $this->position;
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
        $this->initialize();
        return $this->knownSize;
    }

    #[Override]
    public function close(): void
    {
        $this->plainBuffer = '';
        $this->outputBuffer = '';
        $this->position = 0;
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
            if ($this->position !== 0) {
                return '';
            }

            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }
}
