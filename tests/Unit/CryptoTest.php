<?php

namespace Oem\Psr7WhatsappMediaCrypto\Test\Unit;

use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CryptoTest extends TestCase
{
    private MediaCrypto $crypto;

    protected function setUp(): void
    {
        $this->crypto = new MediaCrypto(new MediaKeyExpander());
    }

    public function testEncryptDecrypt(): void
    {
        $data = random_bytes(1024);
        $key = random_bytes(32);

        $enc = $this->crypto->encrypt($data, $key, MediaType::IMAGE->value);
        $dec = $this->crypto->decrypt($enc, $key, MediaType::IMAGE->value);

        $this->assertSame($data, $dec);
    }

    public function testTamperedDataFails(): void
    {
        $this->expectException(RuntimeException::class);

        $data = random_bytes(512);
        $key = random_bytes(32);

        $enc = $this->crypto->encrypt($data, $key, MediaType::IMAGE->value);

        $pos = random_int(0, strlen($enc) - 1);
        $enc[$pos] = chr(ord($enc[$pos]) ^ 1);

        $this->crypto->decrypt($enc, $key, MediaType::IMAGE->value);
    }
}