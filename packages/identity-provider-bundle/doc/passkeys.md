# Passkeys

The module `passkeys` lets an account register passkeys — discoverable WebAuthn credentials — and sign in with them,
without a password. A passkey cannot be phished: the browser signs only for the relying party it was created for.

## Configuration

```yaml
phil_identity_provider:
    modules: { passkeys: true }
    passkeys:
        rp_id: example.com
        origins: ['https://example.com', 'https://www.example.com']
        require_user_verification: false
```

`rp_id` is the registrable domain the passkeys belong to; every origin the pages are served from is listed. With
`require_user_verification`, an authenticator must verify the person by a PIN or a biometric.

## The ceremonies

| Step | Route | What happens |
|---|---|---|
| Registration options | `POST /account/passkeys/options` | Signed in, after a recent proof: a challenge kept in the session for one answer, the options of `navigator.credentials.create()`. |
| Registration | `POST /account/passkeys/register?name=` | The JSON of `PublicKeyCredential.toJSON()`, verified, kept. |
| Sign-in options | `POST /passkey/options` | A challenge; signed out, no credential listed, so the browser offers the passkeys it holds. |
| Sign-in | `POST /passkey/login` | `PasskeyAuthenticator` verifies the answer and signs the account in, or, signed in, re-authenticates it. |

The JSON requests carry the CSRF token in the header `X-CSRF-Token`.

## What is checked

`RelyingParty` checks, at registration:

- the client data: the ceremony `webauthn.create`, the challenge issued, an allowed origin, no cross-origin frame;
- the attestation object, read by a CBOR reader bounded in depth, items and lengths, and its statement as
  [Attestation](#attestation) says;
- the authenticator data: the SHA-256 of the relying party identifier, the user's presence, the verification when
  required, the credential and its COSE key — ES256 on P-256, RS256 of 2048 bits or more, or EdDSA on Ed25519.

At sign-in, the same client data checks with `webauthn.get`, the signature over the authenticator data and the hash of
the client data, the user handle when given, and the counter: when the authenticator keeps one, a value that does not
move forward is refused, as a cloned authenticator would show. Every refusal answers `passkey.refused`, so that a page
tells nothing an attacker could use.

The proofs are `hwk` and `user` (RFC 8176), and `mfa` when the authenticator verified the person.

## Attestation

Every passkey records its AAGUID — the make and model its authenticator names —, whether it may be synced to other
devices (backup eligible, the flag BE) and whether it is (backed up, BS, refreshed at each sign-in), the format of its
attestation statement and what that statement proved. `Passkey::aaguid()`, `isBackupEligible()`, `isBackedUp()`,
`attestationFormat()` and `attestationType()` expose them, and the event `identity.passkey.added` carries the passkey,
`aaguid`, `backup_eligible`, `synced` and `attestation`.

```yaml
phil_identity_provider:
    passkeys:
        attestation:
            conveyance: direct          # none, indirect, direct or enterprise
            trusted_roots: ['%kernel.project_dir%/config/attestation/vendor-root.pem']
            require_trusted: false
            allowed_aaguids: []         # empty for any
            denied_aaguids: []
            backup: allowed             # allowed, required or forbidden
```

With the conveyance `none`, the default, no attestation is asked for and a statement an authenticator sends anyway is
not read: the passkey records the type `none`, or `unverified` for a statement of another format, and its AAGUID is
what the authenticator claims. With another conveyance, `AttestationStatement` reads the statement:

| Statement | What is checked | Type |
|---|---|---|
| `none` | An empty statement. | `none` |
| `packed` without certificates | Signed by the passkey itself, with the algorithm of its key. | `self` |
| `packed` with certificates (`x5c`, at most five) | Signed by the first, a certificate of version 3 of the unit `Authenticator Attestation` that is no authority and names the passkey's AAGUID when it names one; each certificate signed by the next, the last by one of `trusted_roots`, all valid now. | `trusted`, or `unverified` when the chain reaches no trusted root |
| Another format (`tpm`, `android-key`, `apple`, `fido-u2f`, …) | Not read. | `unverified` |

A signature that does not verify, or an attestation certificate that breaks the rules of its format, refuses the
registration with `passkey.refused`. Only the type `trusted` vouches for the AAGUID: the vendor of the root says which
make and model holds the key.

`AttestationPolicy` then decides what the site accepts: `require_trusted` refuses a passkey of another type than
`trusted`; `allowed_aaguids` and `denied_aaguids` accept and refuse makes and models; `backup: forbidden` refuses a
passkey that may be synced, for a site that wants keys bound to one device, and `backup: required` one that may not. A
passkey that breaks the policy is refused with `passkey.not_accepted`, which tells its owner to try another
authenticator. Without `require_trusted`, an AAGUID list keeps honest authenticators out, not a forged one. The roots
are read when the container is built: clear the cache after replacing one.

## The list

`/account/passkeys` lists the passkeys of the account with their name, whether they are synced across devices, when
they were added and last used. A passkey is renamed at once; removing one needs a recent proof, and the last way to sign
in — the last passkey of an account without a password — cannot be removed.

## The Stimulus controller

The templates `passkey/passkeys.html.twig` and `passkey/_sign_in.html.twig` drive the controller `passkey` of
`@phil/identity-provider-bundle`, which uses `PublicKeyCredential.parseCreationOptionsFromJSON()`,
`parseRequestOptionsFromJSON()` and `toJSON()`; a browser without them shows a message. A page of the application
includes `_sign_in.html.twig` beside its sign-in form.
