# Encrypting personal data at rest

The bundle declares, exports, erases and anonymizes personal data; it does not encrypt it. Columns of personal data
are encrypted at rest by Symfony's KeyManagement component, through two attributes of its Doctrine ORM bridge,
`symfony/doctrine-orm-key-management`: `#[Encrypted]` on the column of a property, and `#[BlindIndexed]` on a sibling
column that keeps a searchable trace of it. Both are experimental in Symfony 8.2, outside Symfony's backward
compatibility promise.

## Which columns to encrypt

Encrypt the columns whose leak would harm the person by itself, and that the application reads by the row rather
than searches or sorts:

| Encrypt | Leave in clear |
|---|---|
| Phone numbers, postal addresses, birth dates | Identifiers, foreign keys, creation and update dates |
| Health, dietary, accessibility and emergency-contact details | The dates and categories the retention schedule reads |
| Free text a person wrote about themselves: notes, answers to questions | Columns sorted, grouped or compared in SQL |
| Government identifiers, when the application must keep them at all | Whatever a `#[PersonalData]` anonymizer must still rewrite in SQL |

An email address that the application looks people up by is encrypted, and a blind index beside it serves the
lookup. Encryption protects the database, its backups and its replicas from whoever reads them without the keys; it
does not protect the data from the application itself, which can decrypt every row it can load.

`#[Encrypted]` and `#[PersonalData]` go together on the same property: the first says how the value is stored, the
second what it is, for the register, the export, the erasure and the anonymization.

```php
use Doctrine\ORM\Mapping as ORM;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[ORM\Entity]
class Member
{
    #[ORM\Column(length: 180)]
    #[Encrypted(key: 'member.email')]
    #[PersonalData('email', retention: 'R-002')]
    private string $email = '';

    #[ORM\Column(length: 64)]
    #[BlindIndexed('email', 'member-email')]
    private string $emailIndex = '';

    #[ORM\Column(nullable: true)]
    #[Encrypted(key: 'member.contact')]
    #[PersonalData('phone')]
    private ?string $phone = null;
}
```

## Encrypted columns

`#[Encrypted]` encrypts the column of a property by envelope encryption under a master key of the KMS: the column
keeps the Doctrine type of its mapping, which converts the value first, and is stored as a binary column. The value
is authenticated with the key and the type's name, so a value copied to a column of another key does not decrypt.
Give each kind of data its own key: columns that share a key and a type read each other's values.

With a data key store (`key_management.store`), the key may be omitted: each physical column then gets a data key
scope of its own, derived from its schema, table and column names.

The KMS is a client of `key_management.clients`: the application's — AWS KMS, Azure Key Vault, Google Cloud KMS,
HashiCorp Vault Transit — or a local `sodium://` one whose master keys are 32 random bytes in base64url:

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[member.email]=%env(MEMBER_EMAIL_KEY)%&keys[member.contact]=%env(MEMBER_CONTACT_KEY)%'
```

## Blind indexes for searching

Encryption is randomized: `WHERE email = ?` never matches an encrypted column. `#[BlindIndexed]` fills a sibling
column on every flush with a keyed digest of the value, equal for equal values, and the application looks the row up
by that digest:

```php
$repository->findOneBy(['emailIndex' => $memberEmailIndex->of($email)]);
```

The index is a `Symfony\Component\KeyManagement\BlindIndex` service tagged `key_management.blind_index` with its name,
`member-email` here, over a data key minted once with `key-management:generate-data-key` and kept wrapped in the
configuration. Its projection says what of the value is indexed: `Projection\Email` folds an address,
`Projection\EmailDomain` keeps its domain, `Projection\Verbatim` folds nothing, for a phone number already written in
E.164 for instance.

A blind index tells anyone who reads the table which rows share a value and how often: index only what is looked up
by equality and has many distinct values, such as an email address, never a birth date or a postal code. The
listener writes the tag through the ORM only: a row inserted through DBAL or a bulk `UPDATE` keeps a stale tag.

## Rotation

The keys rotate in the KMS and in `symfony/key-management`, not in this bundle, and no command of this bundle
encrypts again the values already written.

With a data key store, the column values refer to stored data keys:

- `rotate($scope)` of the store, `RewrappableDataKeyStoreInterface`, retires the current data key of a scope and
  creates a fresh one: the values written from then on are sealed under it, and those already written keep referring
  to the retired key, which stays in the store and must not be deleted while they remain.
- `key-management:rewrap-data-keys` wraps the stored data keys again under another client or master key, without
  reading or rewriting a column value. The old master key can leave the KMS once every stored key is wrapped again.

Without a store, each value carries its wrapped data key: keep the old master key in the KMS while values sealed under
it remain. Loading and saving the entities encrypts nothing again: Doctrine's unit of work compares the decrypted
values, finds them equal and writes no row, so a master key removed after it destroys every value still sealed under
it.

Rotation limits what one leaked data key reads, not what access to the KMS reads: whoever can call the KMS with the
application's credentials unwraps every data key, retired or current, and so decrypts every column. Restrict that
access to the application, and audit its calls in the KMS.

The data key of a blind index never rotates: every tag already written was derived under it.

## Erasure and anonymization

An encrypted column is erased and anonymized like any other: the bundle's erasers and anonymizers go through the
entities, and the ORM encrypts what they write. A SQL anonymization that bypasses the ORM writes clear text into an
encrypted column, which no longer decrypts; anonymize such columns through the entities, or set them to `NULL`.
