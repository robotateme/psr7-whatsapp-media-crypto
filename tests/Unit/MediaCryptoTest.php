<?php
namespace Oem\Psr7WhatsappMediaCrypto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Random\RandomException;
use RuntimeException;

class MediaCryptoTest extends TestCase
{
    private MediaCrypto $crypto;

    protected function setUp(): void
    {
        $this->crypto = new MediaCrypto(new MediaKeyExpander());
    }

    /**
     * @throws RandomException
     */
    public function testEncryptDecryptRoundTrip(): void
    {
        $data = random_bytes(1024);
        $key = random_bytes(32);

        $enc = $this->crypto->encrypt($data, $key, MediaType::IMAGE->value);
        $dec = $this->crypto->decrypt($enc, $key, MediaType::IMAGE->value);

        $this->assertSame($data, $dec);
    }

    public function testMacValidationFails(): void
    {
        $this->expectException(RuntimeException::class);

        $data = 'test-data';
        $key = random_bytes(32);

        $enc = $this->crypto->encrypt($data, $key, MediaType::IMAGE->value);

        // ломаем MAC
        $pos = strlen($enc) - 1;
        $enc[$pos] = $enc[$pos] ^ chr(1);

        $this->crypto->decrypt($enc, $key, MediaType::IMAGE->value);
    }

    public function testDifferentMediaTypes(): void
    {
        $data = 'same-data';
        $key = random_bytes(32);

        $enc1 = $this->crypto->encrypt($data, $key, MediaType::IMAGE->value);
        $enc2 = $this->crypto->encrypt($data, $key, MediaType::VIDEO->value);

        $this->assertNotSame($enc1, $enc2);
    }
}