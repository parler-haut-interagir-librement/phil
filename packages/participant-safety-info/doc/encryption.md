# Encryption

## How a record is sealed

`SafetyInfoCodec` writes the information as a JSON object of version 1. `KeyManagement\KeyManagementSealer` seals it
by the envelope encryption of Symfony's KeyManagement component:

1. the KMS generates a fresh data key for the record and returns it wrapped by the master key;
2. the text is encrypted locally with AES-256-GCM under that data key, and the data key is wiped;
3. the envelope — the name of the master key, the wrapped data key, the nonce, the tag and the ciphertext — is stored
   in standard base64.

The plaintext never reaches the KMS and never reaches the store.

## What binds a record to its row

The context authenticated with the text is `phil/participant-safety-info`, the version `v1`, the event and the
participant, separated by the unit separator, which no identifier may hold. A record copied to another participant
or another event does not open: the vault refuses it with `Exception\UnreadableSafetyInfo`. A record altered in any
byte does not open either.

## Decoding

The opened text is decoded strictly: 32 KiB at most, a depth of 4, exactly the five keys of version 1, every value
of its type, every field checked again by the constructors. Anything else is `UnreadableSafetyInfo`.

## The master key

The sealer takes the name of the master key to seal with. Each envelope names the master key that sealed it, so a
record sealed before the configured name changed still opens while the KMS holds the old key. To rotate, configure the
new name: new records use it, older ones open under theirs until they are purged with their event.

## What stays in plaintext

The store sees the event, the participant, when the record was sealed and until when it is kept: what it needs to
find and purge a record. `SafetyInfo` and `EmergencyContact` never dump their fields: `var_dump()`, `print_r()` and
the debuggers show `[sensitive]`, and their constructors mark every field `#[SensitiveParameter]`, so a stack trace
never shows them.
