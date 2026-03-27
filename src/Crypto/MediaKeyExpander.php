<?php

namespace Oem\Psr7WhatsappMediaCrypto\Crypto;
final class MediaKeyExpander
{
    public function expand(string $mediaKey, string $info): array
    {
        $expanded = hash_hkdf(
            'sha256',
            $mediaKey,
            112,
            $info,
            ''
        );

        return [
            'iv'        => substr($expanded, 0, 16),
            'cipherKey' => substr($expanded, 16, 32),
            'macKey'    => substr($expanded, 48, 32),
        ];
    }
}