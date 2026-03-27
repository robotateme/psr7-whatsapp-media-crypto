<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;

$crypto = new MediaCrypto(new MediaKeyExpander());

// 1. исходные данные
$original = "Hello WhatsApp Crypto!\n" . random_bytes(32);

// 2. ключ (32 байта)
$key = random_bytes(32);

// 3. шифрование
$encrypted = $crypto->encrypt($original, $key, MediaType::IMAGE->value);

// 4. сохраняем
file_put_contents(__DIR__ . '/samples/image.original', $original);
file_put_contents(__DIR__ . '/samples/image.key', base64_encode($key));
file_put_contents(__DIR__ . '/samples/image.encrypted', $encrypted);

echo "Samples generated". PHP_EOL;