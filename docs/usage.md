# Использование

## Базовый API

```php
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;

$crypto = new MediaCrypto(new MediaKeyExpander());

$encrypted = $crypto->encrypt($data, $key, 'WhatsApp Image Keys');
$decrypted = $crypto->decrypt($encrypted, $key, 'WhatsApp Image Keys');
```

## Stream API

```php
use GuzzleHttp\Psr7\Utils;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaCrypto;
use Oem\Psr7WhatsappMediaCrypto\Crypto\MediaKeyExpander;
use Oem\Psr7WhatsappMediaCrypto\Enum\MediaType;
use Oem\Psr7WhatsappMediaCrypto\Stream\DecryptingStream;
use Oem\Psr7WhatsappMediaCrypto\Stream\EncryptingStream;

$crypto = new MediaCrypto(new MediaKeyExpander());
$key = random_bytes(32);

$plainStream = Utils::streamFor(fopen('/path/to/file', 'rb'));
$encryptingStream = new EncryptingStream($plainStream, $crypto, $key, MediaType::IMAGE);

while (!$encryptingStream->eof()) {
    $cipherChunk = $encryptingStream->read(8192);
    // write chunk to network or storage
}

$cipherStream = Utils::streamFor($ciphertext);
$decryptingStream = new DecryptingStream($cipherStream, $crypto, $key, MediaType::IMAGE);
$plaintext = $decryptingStream->getContents();
```

## Замечания

- `EncryptingStream` реализует настоящее инкрементальное потоковое шифрование.
- `DecryptingStream` сначала проверяет MAC и открывает plaintext только после завершения аутентификации.
- Для ручных нагрузочных проверок см. [Сценарии производительности и нагрузки](performance.md).
