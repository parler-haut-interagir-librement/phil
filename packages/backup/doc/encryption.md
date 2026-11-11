# Encryption

## Keys

A `BackupKey` is 32 random bytes. `BackupKey::generate()` draws one, and `toBase64()` writes it for the place the
application keeps its secrets; `BackupKey::fromBase64($value)` reads it back, white space around it ignored. A value
that is not 32 bytes of standard base64 is refused with `InvalidBackupValue`:

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

**Whoever holds the key reads every backup; whoever loses it reads none.** Keep it apart from the backups — in a
secret store, not in the same bucket — and keep a copy where a disaster that takes the server does not take the key.
To change keys, keep the old one until the last set encrypted with it has been rotated out.

## The format

`BackupCipher::encrypt($source, $target)` writes:

| Bytes | Content |
|---|---|
| 7 | the magic `PHILBAK` |
| 1 | the format version, `1` |
| 24 | the header of libsodium's secret stream (XChaCha20-Poly1305) |
| for each chunk: 4 | the length of the chunk's ciphertext, big-endian |
| for each chunk: length | the ciphertext of up to 64 KiB of the file, with its 17 bytes of tag and authentication |

Each chunk is authenticated with the magic and the version as additional data, and the last one carries the stream's
final tag; an empty file is one empty final chunk. A file of any size passes through a fixed amount of memory.

## What is refused

`decrypt($source, $target)` refuses, with `BackupFailed`:

- a file that does not start with the magic: `… is not an encrypted backup.`;
- another version: `… is encrypted in a format this version does not read.`;
- another key, a changed byte, chunks reordered, a file cut short — even at a chunk boundary, since the final tag is
  then missing —, or bytes after the final chunk: `… cannot be decrypted: the key is not the one it was encrypted
  with, or the file is damaged.`

The target receives the chunks as they are authenticated: on a failure, it holds a beginning that must not be used.
`Backups` decrypts into a temporary file it removes whatever happens, and restores nothing from a file that failed.

## Encrypted sets

With a cipher, `Backups::create()` writes each file of the set to a temporary file in clear, encrypts it to its
place with the `.enc` extension, and removes the clear copy. A set is read by its files' extensions: without a key, a
set in clear restores, and an encrypted set is refused with `The backup … is encrypted, and no backup key is given.`

**With a key, a file in clear is refused**, whether `find()` is given it, or `restore()` or `verify()` reads it, with
`The backup … is in clear, and a backup key is given: sets in clear are refused unless they are allowed.` Anyone able
to write to the backup storage could have put it there, and nothing authenticates it: a dump dated in the future would
otherwise be the latest set, and an archive in clear beside a genuine `.sql.enc` would write chosen files into the
media directories. `find(null)` looks at the encrypted sets alone, and a database file is paired only with a media
archive of the same encryption. The sixth argument of `Backups`, `$allowUnencrypted`, accepts the sets in clear again,
for those written before the key was given.
