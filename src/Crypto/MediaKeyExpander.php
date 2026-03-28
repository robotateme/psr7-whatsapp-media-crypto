<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Crypto;
use RuntimeException;
/**
 * @psalm-immutable
*/
final class MediaKeyExpander
{
    private const string HKDF_ALGO = 'sha256';
    private const int EXPANDED_LENGTH = 112;

    /**
     * @param string $mediaKey
     * @param string $info
     * @return ExpandedKeys
     * @psalm-pure
     * @psalm-suppress TypeDoesNotContainType
     */
    public function expand(string $mediaKey, string $info): ExpandedKeys
    {
        if (strlen($mediaKey) !== 32) {
            throw new RuntimeException('Invalid media key length, expected 32 bytes');
        }
        $expanded = hash_hkdf(
            self::HKDF_ALGO,
            $mediaKey,
            self::EXPANDED_LENGTH,
            $info,
        );

        /**
         *  Здесь может вернуть FALSE при:
         *  неверный алгоритм ($algo)
         *  ошибки в параметрах
         *  внутренняя ошибка
        */
        if ($expanded === false || strlen($expanded) !== self::EXPANDED_LENGTH) {
            throw new RuntimeException('HKDF expansion failed');
        }

        return new ExpandedKeys(
            iv: substr($expanded, 0, 16),
            cipherKey: substr($expanded, 16, 32),
            macKey: substr($expanded, 48, 32),
        );
    }
}