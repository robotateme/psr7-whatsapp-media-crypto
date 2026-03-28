<?php
declare(strict_types=1);

namespace Oem\Psr7WhatsappMediaCrypto\Sidecar;
/**
 * @psalm-immutable
 * @psalm-suppress UnusedClass
*/
final class SidecarGenerator
{
    /**
     * @param string $enc
     * @param string $macKey
     * @return string
     * @psalm-pure
     */
    public function generate(string $enc, string $macKey): string
    {
        $enc = substr($enc, 0, -10);

        $out = '';
        for ($i = 0, $iMax = strlen($enc); $i < $iMax; $i += 65536) {
            $chunk = substr($enc, $i, 65536 + 16);
            $mac = hash_hmac('sha256', $chunk, $macKey, true);
            $out .= substr($mac, 0, 10);
        }

        return $out;
    }
}