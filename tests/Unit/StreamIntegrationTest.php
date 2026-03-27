<?php

namespace Oem\Psr7WhatsappMediaCrypto\Test\Unit;


use Oem\Psr7WhatsappMediaCrypto\Stream\EncryptingStream;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Utils;
use Oem\Psr7WhatsappMediaCrypto\Stream\DecryptingStream;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Random\RandomException;

class StreamIntegrationTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function testStreamEncryptDecrypt(): void
    {
        $data = random_bytes(2048);
        $key = random_bytes(32);

        $crypto = new MediaCrypto(new MediaKeyExpander());

        $encStream = new EncryptingStream(
            Utils::streamFor($data),
            $crypto,
            $key,
            MediaType::IMAGE
        );

        $encrypted = $encStream->getContents();

        $decStream = new DecryptingStream(
            Utils::streamFor($encrypted),
            $crypto,
            $key,
            MediaType::IMAGE
        );

        $result = $decStream->getContents();

        $this->assertSame($data, $result);
    }
}