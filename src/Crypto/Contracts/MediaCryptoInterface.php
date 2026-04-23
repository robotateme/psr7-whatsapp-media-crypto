<?php

namespace Oem\Psr7WhatsappMediaCrypto\Crypto\Contracts;

/**
 * @psalm-immutable
 */
interface MediaCryptoInterface
{
    /**
     * @psalm-pure
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function encrypt(string $plain, string $mediaKey, string $type): string;
    /**
     * @psalm-pure
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function decrypt(string $cipher, string $mediaKey, string $type): string;
}
