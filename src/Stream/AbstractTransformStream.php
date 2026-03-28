<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Stream;

use Override;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use RuntimeException;
use Throwable;

abstract class AbstractTransformStream implements StreamInterface
{
    use StreamDecoratorTrait;

    protected bool $initialized = false;
    protected string $buffer = '';
    protected int $position = 0;
    protected StreamInterface $stream;

    /**
     * @param StreamInterface $stream
     * @psalm-pure
     */
    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * @param string $data
     * @return string
     * @psalm-pure
     */
    abstract protected function transform(string $data): string;

    /**
     * @return void
     */
    protected function init(): void
    {
        if ($this->initialized) {
            return;
        }
        if ($this->stream->isSeekable()) {
            $this->stream->rewind();
        }

        $data = '';

        while (!$this->stream->eof()) {
            $data .= $this->stream->read(8192);
        }

        $this->buffer = $this->transform($data);
        $this->initialized = true;
    }

    /**
     * @param int $length
     * @return string
     */
    #[Override]
    public function read(int $length): string
    {
        $this->init();

        if ($this->position >= strlen($this->buffer)) {
            return '';
        }

        $chunk = substr($this->buffer, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    /**
     * @return string
     */
    #[Override]
    public function getContents(): string
    {
        $this->init();

        if ($this->position >= strlen($this->buffer)) {
            return '';
        }

        $remaining = substr($this->buffer, $this->position);
        $this->position = strlen($this->buffer);

        return $remaining;
    }

    /**
     * @return bool
     */
    #[Override]
    public function eof(): bool
    {
        $this->init();
        return $this->position >= strlen($this->buffer);
    }

    /**
     * @return int
     */
    #[Override]
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * @return void
     */
    #[Override]
    public function rewind(): void
    {
        $this->init();
        $this->position = 0;
    }

    /**
     * @return bool
     * @psalm-mutation-free
     * @psalm-pure
     */
    #[Override]
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return void
     * @psalm-mutation-free
     * @psalm-pure
     */
    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('Stream is not seekable');
    }

    /**
     * @return int|null
     * @psalm-mutation-free
     */
    #[Override]
    public function getSize(): ?int
    {
        return $this->initialized ? strlen($this->buffer) : null;
    }

    /**
     * @return void
     * @psalm-mutation-free
     * @psalm-suppress ImpurePropertyAssignment
     */
    #[Override]
    public function close(): void
    {
        $this->buffer = '';
        $this->position = 0;
        $this->initialized = false;
    }

    /**
     * @return null
     * @psalm-mutation-free
     * @psalm-suppress UnusedMethodCall
     */
    #[Override]
    public function detach(): null
    {
        $this->close();
        return null;
    }

    /**
     * @return string
     */
    #[Override]
    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }
}