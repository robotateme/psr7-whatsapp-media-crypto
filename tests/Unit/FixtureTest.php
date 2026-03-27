<?php

namespace Oem\Psr7WhatsappMediaCrypto\Test\Unit;

use PHPUnit\Framework\TestCase;


use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;

class FixtureTest extends TestCase
{
    public function testSamples(): void
    {
        $crypto = new MediaCrypto(new MediaKeyExpander());

        foreach (glob(__DIR__ . '/../../samples/*.original') as $file) {
            $base = substr($file, 0, -9);
            $original = file_get_contents($base . '.original');
            $key = file_get_contents($base . '.key');
            $expected = file_get_contents($base . '.encrypted');

            // чаще всего ключ base64
            if (strlen($key) !== 32) {
                $key = base64_decode(trim($key));
            }

            $enc = $crypto->encrypt($original, $key, MediaType::IMAGE->value);

            $this->assertTrue(
                hash_equals($expected, $enc),
                "Encrypt mismatch for $base"
            );

            $dec = $crypto->decrypt($enc, $key, MediaType::IMAGE->value);

            $this->assertSame($original, $dec);
        }
    }
}