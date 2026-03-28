<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Crypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\Contracts\MediaCryptoInterface;
use RuntimeException;

final readonly class MediaCrypto implements MediaCryptoInterface
{
    private const int MAC_LENGTH = 10;
    public function __construct(
        private MediaKeyExpander $expander
    ) {}

    public function encrypt(string $plain, string $mediaKey, string $type): string
    {
        $keys = $this->expander->expand($mediaKey, $type);
        $enc = openssl_encrypt(
            $plain,
            'aes-256-cbc',
            $keys->cipherKey,
            OPENSSL_RAW_DATA,
            iv: $keys->iv
        );

        if ($enc === false) {
            throw new RuntimeException('Unable to encrypt data');
        }

        $mac = substr(
            hash_hmac('sha256', $keys->iv . $enc, $keys->macKey, true),
            0,
            self::MAC_LENGTH
        );

        return $enc . $mac;
    }

    public function decrypt(string $cipher, string $mediaKey, string $type): string
    {
        $keys = $this->expander->expand($mediaKey, $type);

        if (strlen($cipher) < self::MAC_LENGTH) {
            throw new RuntimeException('Ciphertext too short');
        }

        $file = substr($cipher, 0, -self::MAC_LENGTH);
        $mac  = substr($cipher, -self::MAC_LENGTH);

        $calcMac = substr(
            hash_hmac('sha256', $keys->iv . $file, $keys->macKey, true),
            0,
            self::MAC_LENGTH
        );

        if (!hash_equals($mac, $calcMac)) {
            throw new RuntimeException('Invalid MAC');
        }

        if (strlen($file) % 16 !== 0) {
            throw new RuntimeException('Invalid ciphertext length');
        }

        $plain = openssl_decrypt(
            $file,
            'aes-256-cbc',
            $keys->cipherKey,
            OPENSSL_RAW_DATA,
            $keys->iv
        );

        if ($plain === false) {
            throw new RuntimeException('Unable to decrypt data');
        }


        return $plain;
    }
}