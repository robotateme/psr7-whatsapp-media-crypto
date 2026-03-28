<?php
declare(strict_types=1);

namespace Oem\Psr7WhatsappMediaCrypto\Stream;

use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Override;
use Psr\Http\Message\StreamInterface;
/**
 * @psalm-suppress UnusedClass
*/
final class EncryptingStream extends AbstractTransformStream
{
    /**
     * @param StreamInterface $stream
     * @param MediaCrypto $crypto
     * @param string $key
     * @param MediaType $type
     * @psalm-mutation-free
     */
    public function __construct(
        StreamInterface              $stream,
        private readonly MediaCrypto $crypto,
        private readonly string      $key,
        private readonly MediaType $type
    )
    {
        parent::__construct($stream);
    }

    /**
     * @param string $data
     * @return string
     * @psalm-mutation-free
     */
    #[Override]
    protected function transform(string $data): string
    {
        return $this->crypto->encrypt(
            $data,
            $this->key,
            $this->type->value
        );
    }
}