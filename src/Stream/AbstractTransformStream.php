<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Stream;

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

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    abstract protected function transform(string $data): string;

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

    public function read($length): string
    {
        $this->init();

        if ($this->position >= strlen($this->buffer)) {
            return '';
        }

        $chunk = substr($this->buffer, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

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

    public function eof(): bool
    {
        $this->init();
        return $this->position >= strlen($this->buffer);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function rewind(): void
    {
        $this->init();
        $this->position = 0;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new RuntimeException('Stream is not seekable');
    }

    public function getSize(): ?int
    {
        return $this->initialized ? strlen($this->buffer) : null;
    }

    public function close(): void
    {
        $this->buffer = '';
        $this->position = 0;
        $this->initialized = false;
    }

    public function detach(): null
    {
        $this->close();
        return null;
    }

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