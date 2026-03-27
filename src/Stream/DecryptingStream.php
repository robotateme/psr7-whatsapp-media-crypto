<?php
namespace Oem\Psr7WhatsappMediaCrypto\Stream;

use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Psr\Http\Message\StreamInterface;

final class DecryptingStream extends AbstractTransformStream
{
    public function __construct(
        StreamInterface              $stream,
        private readonly MediaCrypto $crypto,
        private string               $key,
        private readonly MediaType   $type
    )
    {
        parent::__construct($stream);
    }

    protected function transform(string $data): string
    {
        return $this->crypto->decrypt(
            $data,
            $this->key,
            $this->type->value
        );
    }
}