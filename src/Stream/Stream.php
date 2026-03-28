<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Stream;

use Override;
/**
 * @psalm-suppress UnusedClass
*/
final class Stream extends AbstractTransformStream
{
    /**
     * @param string $data
     * @return string
     * @psalm-pure
     */
    #[Override]
    protected function transform(string $data): string
    {
        return strtoupper($data);
    }
}