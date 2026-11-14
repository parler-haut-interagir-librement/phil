# Conformance

The credentials the bundle issues are Open Badges 3.0 `OpenBadgeCredential`s, secured as Verifiable Credentials 2.0
JSON Web Tokens or with `eddsa-rdfc-2022` Data Integrity proofs. 1EdTech conformance testing of the bundle is in
progress: this version is not certified by 1EdTech, and does not claim to be. Until that testing ends, an application
that relies on the credentials in front of other systems verifies them as this page describes, and can run the
conformance validator itself.

## What is tested today

| Part | How |
|---|---|
| The JSON-LD processing, the canonicalization and the `eddsa-rdfc-2022` proofs | `phil/open-badges` reproduces the W3C test vector of the cryptosuite and passes every evaluation test of the W3C RDF Dataset Canonicalization suite. |
| The tokens | `phil/open-badges` verifies each token with the algorithm of the key, never of the token, and checks its registered claims against the credential. |
| The issued credentials | The credentials the bundle's tests issue are verified again with `CredentialVerifier`: as tokens, as documents with their proof, and baked into PNG and SVG images through the verification page. |
| The published documents | The tests read the issuer's profile, its JSON Web Key Set and its status lists from the routes, and check the keys and the bits they publish. |
| The keys of other issuers | Profiles and `did:web` documents read with Ed25519 and RSA keys, every refusal tested, and the reader fuzzed ([Other issuers](remote-issuers.md)). |
| The shape of a credential | `phil/certificates-bundle` checks offline the shape of the credentials it exports against what Open Badges 3.0 describes. |

## Verifying a credential today

Each of these reads a credential the bundle issued; the last two need nothing of the bundle:

1. **The verification page.** Import `config/verification_routes.php` and open `/verify` under its prefix: it checks a
   token or a document pasted, or a baked PNG or SVG, for its proof, its period, its status and, when given, its
   recipient's identity ([Verification page](verification-page.md)).
2. **The `CredentialVerifier` service.** In the application, inject `Phil\OpenBadges\Verification\CredentialVerifier`
   and call `verify()` with the token or the document; a credential that does not hold throws `InvalidCredential`
   with its reason.

   ```php
   $verified = $verifier->verify($credential);    // a VerifiedCredential, or InvalidCredential
   $verified->issuer();
   ```

3. **Another verifier.** The issuer's profile at its identifier publishes its key as a `Multikey`, and
   `/issuer/keys` as a JSON Web Key Set; a token carries its `kid` and its `jwk`. A verifier of Verifiable Credentials
   2.0 that supports VC-JWT with `EdDSA` or `RS256`, or the `eddsa-rdfc-2022` cryptosuite, reads them without any
   configuration of this bundle. Status lists are `BitstringStatusListCredential`s at `/status/{list}`.
4. **The conformance validator.** 1EdTech runs its validator on its own site, for an account opened there, against an
   issuer on a public HTTPS host. `phil/certificates-bundle` writes out the procedure — deploy, award, export, give the
   files to the validator, revoke and check again — in its `doc/validation.md`; it applies to any credential this
   bundle secures.

## The JSON-LD contexts

The Open Badges 3.0 and CLR 2.0 contexts are published by 1EdTech under their own terms, so the bundle does not ship
them: the application downloads them once and names them in `contexts` ([Configuration](configuration.md)). Data
Integrity proofs need them; tokens do not.

## Reporting a finding

A verifier or the validator that refuses a credential the bundle issued is a defect to report, with the credential,
the version of the bundle and of `phil/open-badges`, the securing, and the verifier's full report. A finding on the
credential itself — its shape, its proof, its token — belongs to `phil/open-badges`; one on a document the routes
serve — the profile, the keys, a status list — belongs to this bundle.
