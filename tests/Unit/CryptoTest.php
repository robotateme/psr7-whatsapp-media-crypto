<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Test\Unit;

use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
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

    /**
     * @throws RandomException
     */
    public function testMalformedCipherWithValidMacFailsGracefully(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid ciphertext length');

        $key = random_bytes(32);
        $expander = new MediaKeyExpander();
        $keys = $expander->expand($key, MediaType::IMAGE->value);

        $file = "\x01";
        $mac = substr(
            hash_hmac('sha256', $keys->iv . $file, $keys->macKey, true),
            0,
            10
        );

        $this->crypto->decrypt($file . $mac, $key, MediaType::IMAGE->value);
    }

    /**
     * @throws RandomException
     */
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