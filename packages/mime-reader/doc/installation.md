# Installation

```sh
composer require phil/mime-reader
```

The library needs PHP 8.5 or later with `ext-mbstring`, which converts character sets and checks UTF-8,
`ext-iconv`, which converts the character sets mbstring does not know, and `ext-sodium`, whose base64 decoder runs in
constant time. It has no other runtime dependency.

An application builds the reader itself, or registers it as a service: `MimeReader` keeps no state between calls, so
one instance serves every request and every worker.

```yaml
# config/services.yaml
services:
    Phil\MimeReader\Limits:
        arguments:
            $maxBytes: 10485760
    Phil\MimeReader\MimeReader: ~
```
