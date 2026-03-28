<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Test\Unit;

use Oem\Psr7WhatsappMediaCrypto\Stream\Stream;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Utils;

class StreamTest extends TestCase
{
    /**
     * @return void
     */
    public function testReadTransformsData(): void
    {
        $stream = new Stream(Utils::streamFor('hello'));
        $result = $stream->read(5);

        $this->assertSame('HELLO', $result);
    }

    /**
     * @return void
     */
    public function testEof(): void
    {
        $stream = new Stream(Utils::streamFor('abc'));
        $stream->read(5);

        $this->assertTrue($stream->eof());
    }
}