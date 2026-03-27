<?php
namespace Oem\Psr7WhatsappMediaCrypto\Crypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\Contracts\MediaCryptoInterface;
use RuntimeException;

final readonly class MediaCrypto implements MediaCryptoInterface
{
    public function __construct(
        private MediaKeyExpander $expander
    ) {}

    public function encrypt(string $plain, string $mediaKey, string $type): string
    {
        $keys = $this->expander->expand($mediaKey, $type);
        $enc = openssl_encrypt(
            $plain,
            'aes-256-cbc',
            $keys['cipherKey'],
            OPENSSL_RAW_DATA,
            iv: $keys['iv']
        );

        $mac = substr(
            hash_hmac('sha256', $keys['iv'] . $enc, $keys['macKey'], true),
            0,
            10
        );

        return $enc . $mac;
    }

    public function decrypt(string $cipher, string $mediaKey, string $type): string
    {
        $keys = $this->expander->expand($mediaKey, $type);

        $file = substr($cipher, 0, -10);
        $mac  = substr($cipher, -10);

        $calcMac = substr(
            hash_hmac('sha256', $keys['iv'] . $file, $keys['macKey'], true),
            0,
            10
        );

        if (!hash_equals($mac, $calcMac)) {
            throw new RuntimeException('Invalid MAC');
        }

        $plain = openssl_decrypt(
            $file,
            'aes-256-cbc',
            $keys['cipherKey'],
            OPENSSL_RAW_DATA,
            $keys['iv']
        );

        return $plain;
    }
}