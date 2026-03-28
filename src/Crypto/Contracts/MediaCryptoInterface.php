<?php

namespace Oem\Psr7WhatsappMediaCrypto\Crypto\Contracts;

/**
 * @psalm-immutable
 */
interface MediaCryptoInterface
{
    /**
     * @psalm-pure
     */
    public function encrypt(string $plain, string $mediaKey, string $type): string;
    /**
     * @psalm-pure
     */
    public function decrypt(string $cipher, string $mediaKey, string $type): string;
}