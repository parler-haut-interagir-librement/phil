# Signing

The `sign` stage signs a PDF with a certificate; the `timestamp` stage adds a document timestamp from an RFC 3161
authority. Both go through two ports, so the certificate and the authority come from where the application keeps them.

## Certificates

A `Certificate` is a PKCS#12 file (`.p12`, `.pfx`) opened by its password, or a PEM certificate with its PEM private
key:

```php
use Phil\PagedMedia\Gotenberg\Signing\Certificate;

Certificate::pkcs12(InputFile::fromPath('/run/secrets/signer.p12'), $password);
Certificate::pem(InputFile::fromPath('/run/secrets/cert.pem'), InputFile::fromPath('/run/secrets/key.pem'));
```

A `CertificateSource` gives the certificate each time a PDF is signed, so a renewed certificate is taken without a
restart:

| Source | Where the certificate is |
|---|---|
| `FileCertificateSource($path, $keyPath, $password)` | On disk: a PKCS#12 file and its password, or a PEM certificate and the file of its key |
| `InlineCertificateSource($certificate, $key, $password)` | In memory: a PEM certificate and key, or the bytes of a PKCS#12 file, from an environment variable or Symfony's secrets vault; sent as `signer.pem` and `signer.key`, or `signer.p12` |
| `VaultCertificateSource($client, $address, $token, $path, $mount)` | In a key/value secrets engine, version 2, of HashiCorp Vault or OpenBao, read at `<address>/v1/<mount>/data/<path>`; the secret holds `certificate` and `key` in PEM |

A vault out of reach, or a secret of another shape, throws `CertificateUnavailable`. An application with its own vault
implements `CertificateSource`.

## Signatures

```php
use Phil\PagedMedia\Gotenberg\Stage\Sign;

$sign = Sign::with($source, reason: 'Approval', location: 'Montréal', name: 'Ada Lovelace', pades: true, timestampAuthority: $tsa);
$gotenberg->pipe($files, new Pipeline(new Remediate(Profile::PdfA3b), new Optimize(), $sign));
```

`reason`, `location`, `contactInfo` and `name` show in the viewer's signature panel. `pades` makes a PAdES signature,
the format the EU's eIDAS regulation recognises. A timestamp authority given to the signature vouches for its signing
time. A self-signed certificate signs, but viewers flag it as untrusted: sign with one from an authority they trust.

A change after a signature breaks it: only a timestamp, another signature or a terminal stage may follow it, and a
stage that rewrites the file is refused on a signed PDF. An encrypted PDF is signed in two requests: decrypt it, then
sign the result.

## Timestamps

A `TimestampAuthority` gives the URL of an RFC 3161 authority; `TimestampAuthorityUrl` is one known by its URL, such as
one from the configuration. That URL is a public address, read by `phil/url` as a download's is.

```php
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Stage\Timestamp;

$gotenberg->run($files, new Timestamp(new TimestampAuthorityUrl('https://timestamp.example.com')));
```

A document timestamp names no signer: it proves the PDF existed, unchanged, at that time. Gotenberg calls the
authority itself, within what its operator allows (`--pdf-allow-list`, `--pdf-deny-private-ips`).
