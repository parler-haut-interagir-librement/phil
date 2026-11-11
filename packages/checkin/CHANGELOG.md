# Changelog

All notable changes to `phil/checkin` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `CheckpointId`, `DeviceId` and `ScanId`: identifiers of 1 to 64 letters, digits, `.`, `_`, `:` or `-`;
  `ScanId::generate()` draws 128 random bits.
- `CredentialCode`: what a device read, valid UTF-8 without control characters, at most 512 bytes, never recorded;
  `fingerprint()`, its HMAC-SHA256 under a key; `estimatedBits()`, its strength from its length and alphabet.
- `Credential` and `CredentialStatus`: the application's identifier, a label for the staff, the checkpoints it opens,
  its opening and closing, its entries — one by default, or any number — and whether it is revoked.
- `Attendance`: the entries of a credential, whether its holder is inside, the last entry, and the version that
  counts the check-ins that changed it.
- `Direction`, `ScanOutcome` and `AdmissionPolicy`: entries and exits decided in a fixed order — revoked, wrong
  checkpoint, check-out, not yet open with an early window, closed, already admitted under anti-passback or with its
  entries used, admitted.
- `CheckIn`: one scan of a known credential with its decision, the sequence of an admission or a check-out, and the
  conflict of a scan made offline.
- `CredentialResolver`, `CheckInLedger` and `CredentialCatalog`, the ports of the application; `CredentialResolvers`,
  several resolvers asked in their order; `CatalogEntry`, a credential with one of its codes.
- `CheckInDesk` and `ScanResult`: a code resolved, decided and recorded, refusals included, once per scan
  identifier, decided again up to three times when another scan of the credential is recorded first; a scan sent
  again is replayed only when its credential, checkpoint, device and direction match the one recorded, and answered
  `ScanOutcome::Collision` otherwise, without the outcome or the check-in recorded.
- `InMemoryCheckInLedger`, a ledger for tests.
- `ManifestIssuer`, `OfflineManifest` and `ManifestEntry`: the credentials of a checkpoint for a device, under the
  fingerprints of their codes with a key of the manifest, their attendance read in chunks of 500, labels only when
  asked, valid 12 hours by default and bounded to 10 000 credentials by default; codes under 64 bits refused by
  default, since the manifest carries its key.
- `OfflineScan`, `OfflineBatch`, `OfflineSync`, `SyncStatus` and `SyncReport`: the journal of a device read, at most
  500 scans, and recorded by instant with the device's decision, a conflict when the ledger decides otherwise, a
  duplicate when the same scan is already recorded, a collision when its identifier is recorded for another scan,
  rejected when the credential is unknown or the instant out of bounds; the ledger's attendance follows the
  device's claim.
- `CheckinException`, implemented by `InvalidCheckin`, `InvalidOfflineBatch`, `LedgerContention`,
  `ManifestTooLarge` and `WeakManifestCodes`.
- The fuzz target of the journals and the benchmark of a scan, a decision, a fingerprint, a manifest and a journal.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
