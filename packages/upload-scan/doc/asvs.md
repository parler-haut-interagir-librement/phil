# ASVS 5.0 mapping

The requirements of [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) the library meets, with the
test that shows each one, and those it leaves to the application. `phil/upload-scan-bundle` has its own mapping for the
quarantine. The guide of the monorepo, [ASVS 5.0: what the application keeps](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md), gathers what every
package leaves.

## Met by the library

| Requirement | Level | How | Shown by |
|---|---|---|---|
| 5.4.3 Files from untrusted sources scanned by an antivirus | 2 | `ClamdScanner` streams each file to clamd; archives inside archives are opened by clamd | `ClamdScannerTest`; `ClamdTest::testClamdFindsTheEicarFileInsideNestedArchives` against a real clamd |
| 16.5.3 Failing securely, never open | 2 | A file is clean only on an explicit `OK`: an answer cut short, a timeout, a stream that stops, an unknown answer are error results, which the policy rejects or flags | `ClamdScannerTest`, `ClamdResponseTest`, `ScanPolicyTest` |
| 5.2.1 Files only of a size the application can process | 1 | `maxBytes`, the largest file sent; past it, an error result | `ClamdScannerTest` |
| 5.2.3 Archives bounded in size and number of files | 2 | Met with clamd's `MaxScanSize`, `MaxFiles`, `MaxRecursion` and `AlertExceedsMax yes`, without which an archive past a limit comes back clean ([clamd](clamd.md#archives-and-their-limits)) | `ClamdTest::testAnArchiveBeyondMaxFilesPassesUnlessClamdAlertsOnExceededLimits` |
| 16.4.1 Data encoded against injection | 2 | A signature read from clamd is cut to its printable ASCII before it reaches a log or an event | `ClamdResponseTest`, the fuzz target |

## Left to the application

| Requirement | Level | Why it is the application's |
|---|---|---|
| 5.1.1 The types and sizes allowed, documented | 2 | The application's documentation and its `File` or `Image` constraints |
| 5.2.2 The extension matching the content | 1 | The `File` constraint's `mimeTypes` and `extensions` |
| 5.2.4 Quotas per user | 3 | The application's storage |
| 5.2.5 Archives with symbolic links refused | 3 | The code that unpacks an archive; clamd does not judge links |
| 5.2.6 Images of too many pixels refused | 3 | The `Image` constraint's `maxPixels` |
| clamd's version and limits | — | Run ClamAV 1.5.4 or later (1.4.6 on the 1.4 line) with `AlertExceedsMax yes` |
