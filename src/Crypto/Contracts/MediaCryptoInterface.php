<?php

namespace Oem\Psr7WhatsappMediaCrypto\Crypto\Contracts;

interface MediaCryptoInterface
{
    public function encrypt(string $plain, string $mediaKey, string $type): string;
    public function decrypt(string $cipher, string $mediaKey, string $type): string;
}