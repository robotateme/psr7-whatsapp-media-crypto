<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Crypto;
/**
 * @psalm-immutable
*/
final class ExpandedKeys
{

    /**
     * @param string $iv
     * @param string $cipherKey
     * @param string $macKey
     * @psalm-pure
     */
    public function __construct(
        public string $iv,
        public string $cipherKey,
        public string $macKey)
    {
    }
}