<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Test\Unit;


use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use PHPUnit\Framework\TestCase;
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

    /**
     * @throws RandomException
     */
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

    /**
     * @throws RandomException
     */
    public function testDifferentMediaTypes(): void
    {
        $data = 'same-data';
        $key = random_bytes(32);

        $enc1 = $this->crypto->encrypt($data, $key, MediaType::IMAGE->value);
        $enc2 = $this->crypto->encrypt($data, $key, MediaType::VIDEO->value);

        $this->assertNotSame($enc1, $enc2);
    }

    /**
     * @throws RandomException
     */
    public function testKeyExpansionReturnsObject(): void
    {
        $expander = new MediaKeyExpander();

        $keys = $expander->expand(random_bytes(32), 'WhatsApp Image Keys');

        $this->assertSame(16, strlen($keys->iv));
        $this->assertSame(32, strlen($keys->cipherKey));
        $this->assertSame(32, strlen($keys->macKey));
    }

    /**
     * @throws RandomException
     */
    public function testKeyExpansionIsDeterministic(): void
    {
        $expander = new MediaKeyExpander();

        $key = random_bytes(32);

        $a = $expander->expand($key, 'WhatsApp Image Keys');
        $b = $expander->expand($key, 'WhatsApp Image Keys');

        $this->assertSame($a->iv, $b->iv);
        $this->assertSame($a->cipherKey, $b->cipherKey);
        $this->assertSame($a->macKey, $b->macKey);
    }
}