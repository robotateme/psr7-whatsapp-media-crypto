# PSR-7 WhatsApp Media Crypto

[![GitHub Actions](https://github.com/robotateme/psr7-whatsapp-media-crypto/actions/workflows/php.yml/badge.svg?branch=master)](https://github.com/robotateme/psr7-whatsapp-media-crypto/actions/workflows/php.yml)

Implements WhatsApp media encryption using PSR-7 streams.

## CI

The repository includes both CI configs:

- GitHub Actions: [`.github/workflows/php.yml`](.github/workflows/php.yml)
- GitLab CI: [`.gitlab-ci.yml`](.gitlab-ci.yml)

Both pipelines run the same checks:

- `composer validate --strict`
- `vendor/bin/phpunit`
- `vendor/bin/psalm`

## Features

- AES-256-CBC encryption
- HKDF key expansion
- HMAC-SHA256 authentication
- PSR-7 stream decorators
- AES-256-CBC encryption / decryption
- HKDF (SHA-256) key expansion (WhatsApp spec compliant)
- HMAC-SHA256 authentication (10-byte truncated MAC)
- PSR-7 stream decorators
- Incremental encryption without buffering the whole input in memory
- MAC-verified decryption via temporary stream spool
- Sidecar generation for streaming media (video/audio)
- Typed value objects instead of arrays


## Usage

```php
$crypto = new MediaCrypto(new MediaKeyExpander());

$encrypted = $crypto->encrypt($data, $key, 'WhatsApp Image Keys');
$decrypted = $crypto->decrypt($encrypted, $key, 'WhatsApp Image Keys');
```

### Stream usage

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

---

# Design Q&A

## Почему AES-256-CBC, а не GCM?

**Коротко:** так требует спецификация WhatsApp.

**Развернуто:**

* WhatsApp использует CBC + HMAC вместо AEAD
* аутентификация делается вручную через HMAC
* библиотека повторяет поведение протокола, а не “улучшает” его

---

## Почему MAC обрезается до 10 байт?

* это часть спецификации WhatsApp media encryption
* используется truncated HMAC-SHA256
* баланс между безопасностью и размером

---

## Почему сначала проверяется MAC, а потом decrypt?

```php
if (!hash_equals($mac, $calcMac)) {
    throw new RuntimeException('Invalid MAC');
}
```

* защита от padding oracle атак
* предотвращает обработку повреждённых данных
* стандартная практика secure crypto

---

## Почему используется hash_equals?

* защита от timing attack
* обычное сравнение строк небезопасно

---

## Почему HKDF и зачем info (media type)?

```php
hash_hkdf('sha256', $mediaKey, 112, $info, '');
```

* HKDF используется для безопасного derivation ключей
* `info` = domain separation
* разные типы медиа → разные ключи

---

## Почему используется value object, а не array?

```php
new ExpandedKeys($iv, $cipherKey, $macKey);
```

* исключает ошибки вида `$keys['macKey'] vs $keys['mackey']`
* улучшает читаемость
* даёт строгую типизацию

---

## Что именно здесь stream-ится по-настоящему?

* `EncryptingStream` читает source chunk-by-chunk
* AES-CBC считается блоками без полного буферинга входа
* HMAC считается инкрементально и дописывается в конце

---

## Почему decrypt не отдаёт plaintext сразу по мере чтения?

* в формате WhatsApp MAC лежит в конце ciphertext
* до проверки MAC нельзя безопасно отдавать plaintext наружу
* поэтому `DecryptingStream` читает ciphertext кусками, проверяет MAC и складывает plaintext во временный поток

**Trade-off:**

* входной ciphertext не держится целиком в памяти
* но plaintext становится доступен только после полной аутентификации

---

## Почему используется substr вместо поблочного чтения?

* криптография требует точного контроля байтов
* PHP строки быстрее и проще для таких операций
* избегаем лишних аллокаций

---

## Почему нет salt в HKDF?

```php
hash_hkdf(..., salt: '')
```

* соответствует реализации WhatsApp
* `mediaKey` уже считается криптографически случайным

---

## Почему длины захардкожены?

```text
IV = 16
cipherKey = 32
macKey = 32
MAC = 10
```

* это часть протокола
* вынесено в константы для читаемости

---

## Как обрабатываются ошибки OpenSSL?

```php
if ($enc === false) {
    throw new RuntimeException('Encryption failed');
}
```

* OpenSSL может вернуть `false`
* ошибки не игнорируются

---

## Почему используется PSR-7 StreamDecorator?

* позволяет прозрачно оборачивать любой StreamInterface
* интегрируется с Guzzle / HTTP клиентами
* соответствует стандартам PHP ecosystem

---

## Почему sidecar считается от ciphertext?

* это часть WhatsApp streaming модели
* используется для random access playback
* каждый chunk подписывается отдельно

---

## Почему chunk = 64KB + 16?

```text
[n*64K, (n+1)*64K + 16]
```

* 16 байт — размер блока AES
* нужно для корректной расшифровки с offset

---

## Какие есть ограничения?

* decrypt не является progressive-output stream из-за MAC в конце формата
* при decrypt plaintext сначала пишется во временный поток
* зависит от OpenSSL
* `psalm` в CI требует PHP `8.4.3+` из-за текущей ветки `vimeo/psalm`

---

## Use Cases

- WhatsApp Business API media handling
- Secure file storage (encryption at rest)
- Streaming media (video/audio)
- HTTP middleware encryption layer
