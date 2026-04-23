# Overview

`psr7-whatsapp-media-crypto` implements WhatsApp media encryption on top of PSR-7 streams.

## Features

- AES-256-CBC encryption and decryption
- HKDF-SHA256 key expansion compatible with WhatsApp media keys
- HMAC-SHA256 authentication with 10-byte truncated MAC
- PSR-7 stream decorators for encrypt and decrypt flows
- Incremental encryption without buffering the whole input in memory
- Decryption through a temporary spool stream after MAC verification
- Sidecar generation for streaming media
- Typed value objects instead of plain arrays

## Use Cases

- WhatsApp Business API media handling
- Encryption at rest for files
- Streaming media upload pipelines
- HTTP middleware or transport-layer stream transformation

## Related Docs

- [Usage](usage.md)
- [Design Notes](design.md)
- [Performance and Load Scenarios](performance.md)
