# Keys

The application signs with one key, whatever side it plays, and publishes one key set at `phil_lti_jwks`.

## Keys in the table

With `keys.source: dbal` — the default — the bundle keeps its keys in the table `phil_lti_key`:

- each private key is made by `RsaPrivateKey::generate()` of `phil/lti`, `keys.bits` long, and sealed by envelope encryption through Symfony's KeyManagement, under the master key
  `keys.master_key` of the KMS `keys.kms`, the key identifier authenticated with it;
- the first key is made when the table holds none;
- `phil:lti:rotate-keys` makes a new key and retires the others, which stay published for `keys.overlap`, seven days by
  default; `--if-due` rotates only once the signing key is older than `keys.lifetime`, ninety days by default;
- every run of the command removes the keys retired before the overlap.

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[lti]=%env(LTI_MASTER_KEY)%'
```

The KeyManagement component is experimental in Symfony 8.2: one adapter of the bundle, `KeyManagementSealer`, calls it.
An application that seals otherwise aliases `Phil\LtiBundle\Application\Contract\KeySealer` to its own service.

The table holds each private key as the JSON of its private JSON Web Key, sealed; opened, it is read back by
`RsaPrivateKey::fromJson()` as a key of `web-token/jwt-library`, its size checked.

The keys opened are kept in the service until the end of the request; a worker resets them.

## Keys from the environment

With `keys.source: config`, the application gives one private key in PEM, from a secret or the environment, its
identifier, and the public keys of earlier rotations still published:

```yaml
phil_lti:
    keys:
        source: config
        private_key: '%env(file:LTI_PRIVATE_KEY_FILE)%'
        key_id: '2026-11'
        retired_public_keys: ['%env(file:LTI_PREVIOUS_PUBLIC_KEY_FILE)%']
```

The rotation is then the application's: publish the new public key first, switch the private key, keep the previous
public key for the overlap.

## The keys of the other side

The key sets of the platforms and tools are read through `phil/safe-http` and kept an hour in `cache`; an unknown key
reads the set again, at most once a minute per address.
