# Validation

The credentials a certificate carries are Open Badges 3.0 `OpenBadgeCredential`s. 1EdTech conformance testing of the
bundle is in progress: this version is not certified by 1EdTech, and does not claim to be. The bundle's tests verify
every credential with `phil/open-badges` and check its shape offline; 1EdTech's conformance validator, which reads the
credentials the way the wallets and registries that certify against 1EdTech do, is the outside reading this page
prepares, and an application can run it itself with the procedure below.

## Verifying a certificate today

| Who | How |
|---|---|
| Anyone holding the paper or the PDF | Scans the QR code, or types the code in the register: the page says whether the certificate is valid, expired or revoked ([The register](register.md)). |
| Anyone holding the credential | Pastes it, or the credential extracted from the PDF's associated files, into the verification page of `phil/open-badges-bundle`, which checks its proof, its period, its status and, when given, its holder's identity. |
| The application | Calls `CredentialVerifier::verify()` of `phil/open-badges` with the token or the document, or reads the status with `bin/console phil:certificates:status <code>` ([Console commands](console.md)). |
| Another system | Reads the credential with any Verifiable Credentials 2.0 verifier that supports VC-JWT with `EdDSA` or `RS256`, or the `eddsa-rdfc-2022` cryptosuite: the issuer's profile, its keys and its status lists are public routes of `phil/open-badges-bundle`. |

## What the application provides

The JSON-LD contexts of Open Badges 3.0 and CLR 2.0 are published by 1EdTech under their own terms, so the bundle does
not ship them: the application downloads them once, keeps them with its configuration, and names them in
`phil_open_badges.contexts`, which the Data Integrity suite reads.

```yaml
# config/packages/phil_open_badges.yaml
phil_open_badges:
    contexts:
        'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json': '%kernel.project_dir%/config/jsonld/ob-v3p0-context-3.0.3.json'
        'https://purl.imsglobal.org/spec/clr/v2p0/context-2.0.1.json': '%kernel.project_dir%/config/jsonld/clr-v2p0-context-2.0.1.json'
```

The validator also fetches what the credential points to, so the application under test answers on a public HTTPS
address: the issuer's profile and keys, the status list of the credential, the achievement's course page and its
image.

## Where the validator runs

The conformance validator is a service 1EdTech runs on its own site, for the products it certifies; there is no copy
to run offline or in a continuous integration. It reads the credential it is given and fetches, over the public
Internet, everything the credential points to. A run therefore needs:

- an account on 1EdTech's conformance and certification site, where the Open Badges 3.0 issuer tests are opened;
- the application deployed on a public HTTPS host whose certificate a public authority signed, its routes reachable
  without a login;
- the exported credentials of the procedure below.

Before that run, the checks that need no network are the bundle's own: every credential is verified with
`phil/open-badges` in the tests, its shape is checked as the next section says, the PDF's associated file and its
PDF/A-3b profile are checked against a real Gotenberg by `composer test:services`, and the commands below give the
same files the validator reads.

## What is checked offline

`tests/Unit/Application/ExportedShapeTest.php` awards a certificate, exports its credential with
`phil:certificates:export`, once as a token and once as a document with its proof, and checks its shape against what
the public text of Open Badges 3.0 describes. The rules are written out in `tests/Unit/Support/Ob3Shape.php`: no
context document nor JSON Schema of 1EdTech is read or kept, since the bundle ships no file whose holder is a third
party.

| Part | What is checked |
|---|---|
| `@context` | The Verifiable Credentials 2.0 context first, the Open Badges 3.0 context among the others |
| `id`, `type`, `name` | A URI; `VerifiableCredential` and `OpenBadgeCredential`; a name |
| `issuer` | A `Profile` with its `id` |
| `validFrom`, `validUntil` | Dates and times with their offset |
| `credentialSubject` | An `AchievementSubject` with an `id` or an `identifier`; each `IdentityObject` hashed as `sha256$` and 64 hexadecimal digits, with its salt |
| `achievement` | An `Achievement` with its `id`, `name`, `description`, `criteria` — an `id` or a `narrative` — and an `Image` when there is one |
| `credentialStatus` | A `BitstringStatusListEntry` of revocation or suspension, its index a string of digits, its list a URI |
| A token | `alg` EdDSA or RS256, a `kid` or a `jwk`; `iss`, `jti`, `nbf` and `exp` the credential's issuer, id, `validFrom` and `validUntil` |
| A document | A `DataIntegrityProof` of an EdDSA cryptosuite, of purpose `assertionMethod`, by a key of the issuer, its value in base 58 |

What it cannot know is what the validator also fetches: the issuer's profile and keys as another site reads them, the
status list, the course's page and its image, over the public Internet. That reading is the run below.

## The procedure

1. Deploy the application on a public HTTPS host with its issuer, its key and its contexts configured, the routes of
   `phil/open-badges-bundle` and of the register imported.
2. Define a course and award a test certificate to an identity that is yours:

   ```sh
   bin/console phil:certificates:award first-aid "Test Learner" test@example.org --score=100
   ```

3. Export its credential, once secured as a token and once as a document with its proof — `phil_open_badges.securing`
   set to `jwt`, then `data_integrity`, each with its own certificate:

   ```sh
   bin/console phil:certificates:export <code> > credential.jwt
   bin/console phil:certificates:export <code> > credential.json
   ```

4. Give each file to the validator, as its documentation asks: the token as a JWT, the document as a JSON-LD
   credential with an embedded proof. A credential secured with an RSA key (`phil_open_badges.key.algorithm: RS256`)
   is given as a token only, since the Data Integrity proofs are `eddsa-rdfc-2022`.
5. Revoke a certificate, wait for the status list's `max_age`, export it again, and check that the validator reports it
   revoked.
6. Download the certificate's PDF from the register, extract `credential.jwt` or `credential.json` from its associated
   files, and check that it is the exported credential byte for byte.

## What to record

For each run: the version of the bundle and of `phil/open-badges`, the securing, the validator's version and its full
report. A finding the validator raises on the credential's shape is a defect of `phil/open-badges`, reported there with
the exported file; one raised on a fetched resource — the profile, the keys, a status list — is a defect of
`phil/open-badges-bundle`.
