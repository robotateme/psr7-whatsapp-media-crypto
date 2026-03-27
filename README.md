# PSR-7 WhatsApp Media Crypto

Implements WhatsApp media encryption using PSR-7 streams.

## Features

- AES-256-CBC encryption
- HKDF key expansion
- HMAC-SHA256 authentication
- PSR-7 stream decorators
- AES-256-CBC encryption / decryption
- HKDF (SHA-256) key expansion (WhatsApp spec compliant)
- HMAC-SHA256 authentication (10-byte truncated MAC)
- PSR-7 stream decorators (encrypt/decrypt on read)
- Sidecar generation for streaming media (video/audio)
- Typed value objects instead of arrays


## Usage

```php
$crypto = new MediaCrypto(new MediaKeyExpander());

$encrypted = $crypto->encrypt($data, $key, 'WhatsApp Image Keys');
$decrypted = $crypto->decrypt($encrypted, $key, 'WhatsApp Image Keys');
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

## Почему stream НЕ настоящий streaming?

```php
$this->buffer = $this->transform($data);
```

**Осознанное решение:**

* CBC + HMAC требуют целостного ciphertext
* нельзя корректно валидировать MAC по частям
* buffering даёт:

    * детерминированность
    * простоту реализации
    * меньше крипто-ошибок

**Trade-off:**

* больше потребление памяти

---

## Можно ли сделать настоящий streaming?

Да, но:

* нужно chunk-level MAC
* усложняется API
* возрастает риск ошибок

→ в рамках задачи выбран безопасный и простой вариант

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

* не оптимизировано для очень больших файлов (>100MB)
* buffering вместо streaming
* зависит от OpenSSL

---

## Use Cases

- WhatsApp Business API media handling
- Secure file storage (encryption at rest)
- Streaming media (video/audio)
- HTTP middleware encryption layer
