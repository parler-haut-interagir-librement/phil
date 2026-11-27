<!-- phil-dev source-sha256=452e879473afe5b3e74a55c15ed928915b72ed7aa2b451c6d32144845a83f3b3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/standard-webhooks` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- Les en-têtes et les horodatages : `WebhookId` avec `generate()`, `WebhookTimestamp`, `Tolerance`, `WebhookHeaders`.
  `WebhookTimestamp::fromHeader()` montre un en-tête refusé par `InvalidWebhookValue::shown()`, coupé à 32 octets et
  avec les octets de contrôle, les guillemets, les barres obliques inverses et les octets non ASCII échappés, jamais la
  valeur non vérifiée en entier.
- Les en-têtes de signature : `Signature`, `SignatureList`.
- L'enveloppe : `Envelope`, `VerifiedWebhook`.
- Les clés : les interfaces `SigningKey` et `VerificationKey` ; `Ed25519SecretKey`, `Ed25519PublicKey`, `HmacSecret` —
  testées d'après le vecteur de la RFC 8032 (Ed25519) et le vecteur `v1` de Standard Webhooks (HMAC) — et `Keys`, qui
  lit une clé d'après son préfixe.
- La signature et la vérification : `Signer`, `Verifier`, `SignedContent`, `Scheme`, `Base64`.
  `HmacSecret::verifiesAny()` calcule le HMAC une fois pour toute une liste de signatures, et `Verifier` l'emploie pour
  chaque clé HMAC.
- La réception : `WebhookReceiver`.
- Les clés de confiance : le port `VerificationKeyProvider` et `FixedKeys`, dont `fromStrings()` garde les clés hors
  d'une trace d'appels.
- Le dédoublonnage : le port `ProcessedWebhookRepository`.
- Les exceptions sous `Exception\` : `WebhookException`, `InvalidWebhookValue`, `WebhookRejected`, `RejectionReason`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
