<?php

namespace Oem\Psr7WhatsappMediaCrypto\Crypto;

class ExpandedKeys
{

    /**
     * @param string $iv
     * @param string $cipherKey
     * @param string $macKey
     */
    public function __construct(
        public string $iv,
        public string $cipherKey,
        public string $macKey)
    {
    }
}