<?php


namespace Oem\Psr7WhatsappMediaCrypto\Stream;

use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Psr\Http\Message\StreamInterface;

final class EncryptingStream extends AbstractTransformStream
{
    public function __construct(
        StreamInterface              $stream,
        private readonly MediaCrypto $crypto,
        private readonly string      $key,
        private readonly MediaType $type
    )
    {
        parent::__construct($stream);
    }

    protected function transform(string $data): string
    {
        return $this->crypto->encrypt(
            $data,
            $this->key,
            $this->type->value
        );
    }
}