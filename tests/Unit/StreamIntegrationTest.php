<?php
declare(strict_types=1);
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

    /**
     * @throws RandomException
     */
    public function testEncryptingStreamDoesNotConsumeWholeInputOnSmallRead(): void
    {
        $data = random_bytes(128 * 1024);
        $key = random_bytes(32);

        $crypto = new MediaCrypto(new MediaKeyExpander());
        $source = Utils::streamFor($data);

        $encStream = new EncryptingStream(
            $source,
            $crypto,
            $key,
            MediaType::IMAGE
        );

        $chunk = $encStream->read(32);

        $this->assertNotSame('', $chunk);
        $this->assertFalse($source->eof());
    }

    /**
     * @throws RandomException
     */
    public function testDecryptingStreamSupportsChunkedReads(): void
    {
        $data = random_bytes(8192 + 37);
        $key = random_bytes(32);
        $crypto = new MediaCrypto(new MediaKeyExpander());

        $encrypted = $crypto->encrypt($data, $key, MediaType::IMAGE->value);
        $decStream = new DecryptingStream(
            Utils::streamFor($encrypted),
            $crypto,
            $key,
            MediaType::IMAGE
        );

        $result = '';
        while (!$decStream->eof()) {
            $result .= $decStream->read(257);
        }

        $this->assertSame($data, $result);
    }
}
