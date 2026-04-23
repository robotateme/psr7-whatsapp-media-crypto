<?php
declare(strict_types=1);

namespace Oem\Psr7WhatsappMediaCrypto\Test\Support;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

final class TrackingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public int $readCalls = 0;
    public int $bytesRead = 0;

    public function __construct(protected StreamInterface $stream)
    {
    }

    public function read(int $length): string
    {
        $chunk = $this->stream->read($length);
        $this->readCalls++;
        $this->bytesRead += strlen($chunk);

        return $chunk;
    }
}
