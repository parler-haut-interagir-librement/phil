# ASVS 5.0 mapping

The requirements of [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) the bundle meets, beside those
of `phil/upload-scan`, with the test that shows each one, and those it leaves to the application. The guide of the
monorepo, [ASVS 5.0: what the application keeps](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md), gathers what every package leaves.

## Met by the bundle

| Requirement | Level | How | Shown by |
|---|---|---|---|
| 5.3.1 Uploaded files never served from a public folder | 1 | The quarantine is refused under `public/` when the container is built, as written and once symbolic links are followed; no route serves it | `PhilUploadScanBundleTest::testTheQuarantineIsNeverUnderPublic`, `testTheQuarantineIsNeverUnderPublicThroughASymbolicLink` |
| 5.3.2 File paths from generated data, never from names sent | 1 | Each quarantined file is named by 32 random hexadecimal digits; the name sent is only stored in its record | `FilesystemQuarantineTest` |
| 16.4.1 Data encoded against injection | 2 | The name sent is kept without its control characters and cut to 255 bytes | `FilesystemQuarantineTest`, the fuzz target |
| 16.5.3 Failing securely | 2 | A file that cannot be scanned is refused under `on_error: reject`; a quarantine that cannot keep a file still refuses it | `UploadScannerTest`, `CleanFileValidatorTest` |
| 3.5.1 Requests that change state checked for their origin | 1 | The deletions of the administration page need a CSRF token | [CSRF](csrf.md) and its tests |

## Left to the application

| Requirement | Level | Why it is the application's |
|---|---|---|
| 5.4.1, 5.4.2 File names served and encoded | 2 | The headers of the response that serves a file: `phil/browser-security-bundle`'s user content |
| The order of validation and vote | — | With `#[MapUploadedFile]` as the subject of `#[IsGranted]`, check the account first ([validation](validation.md#check-access-before-the-scan)) |
