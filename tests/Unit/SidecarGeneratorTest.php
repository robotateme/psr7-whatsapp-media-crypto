<?php
declare(strict_types=1);

namespace Oem\Psr7WhatsappMediaCrypto\Test\Unit;

use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Oem\Psr7WhatsappMediaCrypto\Sidecar\SidecarGenerator;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

class SidecarGeneratorTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function testGenerateForSingleChunk(): void
    {
        $plain = random_bytes(2048);
        $mediaKey = random_bytes(32);

        $crypto = new MediaCrypto(new MediaKeyExpander());
        $enc = $crypto->encrypt($plain, $mediaKey, MediaType::IMAGE->value);

        $expanded = new MediaKeyExpander()->expand($mediaKey, MediaType::IMAGE->value);
        $expected = substr(
            hash_hmac('sha256', substr($enc, 0, -10), $expanded->macKey, true),
            0,
            10
        );

        $sidecar = new SidecarGenerator()->generate($enc, $expanded->macKey);

        $this->assertSame($expected, $sidecar);
        $this->assertSame(10, strlen($sidecar));
    }

    /**
     * @throws RandomException
     */
    public function testGenerateForMultipleChunksWithOverlap(): void
    {
        $plain = random_bytes(70000);
        $mediaKey = random_bytes(32);

        $crypto = new MediaCrypto(new MediaKeyExpander());
        $enc = $crypto->encrypt($plain, $mediaKey, MediaType::IMAGE->value);

        $expanded = new MediaKeyExpander()->expand($mediaKey, MediaType::IMAGE->value);
        $cipherWithoutMac = substr($enc, 0, -10);

        $firstChunk = substr($cipherWithoutMac, 0, 65536 + 16);
        $secondChunk = substr($cipherWithoutMac, 65536, 65536 + 16);

        $expected = substr(hash_hmac('sha256', $firstChunk, $expanded->macKey, true), 0, 10)
            . substr(hash_hmac('sha256', $secondChunk, $expanded->macKey, true), 0, 10);

        $sidecar = new SidecarGenerator()->generate($enc, $expanded->macKey);

        $this->assertSame($expected, $sidecar);
        $this->assertSame(20, strlen($sidecar));
    }
}