<!-- phil-dev source-sha256=dda7c0a38f4acee859c24278801f527d02bdbf3f248f620b4387636dca46b0ad -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/guardian-consent` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/service-contracts` 3.8).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `CalendarDate` et `LeapDayRule` : des jours du calendrier, lus de `YYYY-MM-DD` ou d'un instant dans un fuseau horaire,
  leurs anniversaires, avec le 29 février au 1er mars ou au 28 février dans une année commune, et les années entières
  révolues.
- `Jurisdiction` et `Jurisdictions` : l'âge du consentement, l'âge de la majorité, le fuseau horaire et la règle du jour
  bissextile d'un lieu nommé par son code ISO 3166, avec le Québec — 14 et 18 — par défaut.
- `AgeAssessment` et `AgeBracket` : l'âge d'un membre d'après une date de naissance qui n'est pas gardée, sous l'âge du
  consentement, de celui-ci à la majorité, ou adulte.
- `MemberId` et `GuardianId`, des identifiants stables qui refusent un `@` ; `Guardian`, `Feature` et `NoticeVersion`.
- `GuardianConsent` : `register()`, `minor()`, `status()`, `allows()`, `request()`, `open()`, `decide()`, `revoke()`,
  `handOver()` et `sweep()`, avec le consentement fonctionnalité par fonctionnalité, refusé par défaut, cinq demandes en
  attente au plus, une durée de vie et une conservation configurables, et la passation le jour de l'âge du consentement.
- `ConsentStatus`, `Minor`, `ConsentRequest`, `ConsentProof`, `Decision`, `Actor` et `SweepReport`.
- `ConfirmationToken` et `TokenHasher` : des liens de confirmation signés et à usage unique dont le vérificateur est
  stocké comme son HMAC-SHA-256 sous la clé de l'application, avec la rotation des clés.
- Les ports `MinorStore`, `RequestStore`, `ProofStore`, `Notifier`, `GuardianLink` et `SubjectRights` ; les stockages en
  mémoire, qui implémentent `ResetInterface` ; `NoGuardianLink` ; et `GuardianConsentRights`, l'accès et l'effacement de
  ce que la bibliothèque garde sur un membre ou un tuteur.
- Les événements `ConsentRequested`, `ConsentDecided`, `ConsentRevoked`, `RequestErased`, `HandoverDue` et
  `HandoverCompleted`, qui ne portent que des identifiants.
- `GuardianConsentException`, implémentée par `InvalidIdentifier`, `InvalidConsent`, `InvalidConfiguration`,
  `UnknownJurisdiction`, `InvalidToken`, `ExpiredRequest`, `ConsentNotRequired` et `NotAGuardian`.
- Une cible de fuzzing pour le lecteur des jetons de confirmation.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
