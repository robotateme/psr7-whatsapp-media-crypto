<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Stream;

final class Stream extends AbstractTransformStream
{
    protected function transform(string $data): string
    {
        return strtoupper($data);
    }
}