# Design Notes

## Why AES-256-CBC instead of GCM?

WhatsApp media encryption uses CBC plus HMAC. The library follows the protocol instead of replacing it with an AEAD mode.

## Why is the MAC truncated to 10 bytes?

That is part of the WhatsApp media format. The library uses truncated HMAC-SHA256 exactly for compatibility.

## Why verify MAC before decrypt?

This avoids processing tampered ciphertext and protects against padding-oracle style failures.

## Why HKDF and `info` per media type?

HKDF performs deterministic key derivation from the 32-byte media key, and the `info` string provides domain separation between image, audio, video, and document payloads.

## What is truly streamed?

- `EncryptingStream` reads the source chunk-by-chunk
- AES-CBC is processed block-by-block
- HMAC is updated incrementally and appended at the end

## Why does decrypt not emit plaintext progressively?

The WhatsApp format places the MAC at the end of the ciphertext. Plaintext should not be released before the MAC is verified, so `DecryptingStream` spools plaintext into a temporary stream and makes it available only after full authentication.

## Why use typed value objects?

`ExpandedKeys` avoids array-key mistakes and keeps key material explicit.

## Why does sidecar use ciphertext?

That matches the WhatsApp streaming model for random-access playback.

## Protocol Constants

```text
IV = 16
cipherKey = 32
macKey = 32
MAC = 10
```

## Current Limits

- decrypt is not a progressive-output stream
- decrypt writes plaintext to a temporary stream before exposing it
- the library depends on OpenSSL
- current CI static analysis uses a `vimeo/psalm` line that expects PHP `8.4.3+`
