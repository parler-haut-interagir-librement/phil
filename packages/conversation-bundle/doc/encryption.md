# Encryption at rest

## What is sealed

The subject of each conversation and the body of each message are sealed before they reach the database, and opened
when they are read. The identifiers, the moments, the sequences and the reception settings are not: the database knows
who wrote to whom and when, not what they said.

## The format

`KeyManagementMessageSealer` uses the envelope encryption of Symfony's KeyManagement component: a fresh data key per
text, wrapped by a master key of the key management service, the text encrypted locally with AES-256-GCM. A sealed
text reads:

```text
pcv1.a256gcm.<master key>.<envelope>
```

| Part | What it is |
|---|---|
| `pcv1` | The version of the bundle's format |
| `a256gcm` | The identifier of its algorithm: the component's self-contained envelope, AES-256-GCM |
| `<master key>` | The name of the master key, in base64url without padding |
| `<envelope>` | The component's envelope, its first byte naming its format, in base64url without padding |

The version and the algorithm are authenticated with the text, so that neither can be swapped for another; the name of
the master key is checked against the one the envelope carries. Each text is bound to its row: a subject to
`phil_conversation.subject.<conversation>`, a body to `phil_conversation.message.<message>`. A sealed text copied to
another row does not open there. A text that does not open throws `UnreadableMessage`.

A later format takes a new version, and the sealer opens the texts of each version it knows.

## The master keys

A text opens under the master key it names, whichever key is current, so that the texts sealed before a change of key
keep opening while the key management service holds their key. Each row keeps the name of its master key beside the
sealed text, in `subject_key` and `body_key`, so that the texts of a previous key are found without opening them.

## Rotating the master key

1. Add the new key to the client, keeping the previous one:

    ```yaml
    key_management:
        clients:
            local: 'sodium://?keys[conversations]=%env(OLD_KEY)%&keys[conversations-2027]=%env(NEW_KEY)%'
    ```

2. Make the new key current; the new texts are sealed under it from then on:

    ```yaml
    phil_conversation:
        encryption:
            key: conversations-2027
    ```

3. Seal the old texts again, by batches:

    ```sh
    bin/console phil:conversation:rotate-keys --dry-run   # how many remain
    bin/console phil:conversation:rotate-keys --batch=500
    ```

4. Once none remains, remove the previous key from the client.

Each text is opened under the key it names and written back only if no other request changed it meanwhile; a text
whose key has already left the service stops the command with `UnreadableMessage`. `KeyRotation` does the same from
code: `pending()` counts, `rotate($batch)` seals one batch of each again.

## What it does not do

The bundle seals at rest, not end to end: the application reads the texts to show them, and anyone who holds both the
database and the key management service reads them too. The sealing protects a copy of the database, a backup or a
replica, taken without the keys.
