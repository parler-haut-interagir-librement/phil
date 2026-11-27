<!-- phil-dev source-sha256=574af43e38dcc658655085308b42b56ebf253411f247b8293ea40f63a23b3960 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/api-contract` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- Les détails de problème : `Problem\ProblemDetails`.
- Les signaux d'obsolescence : `Deprecation\DeprecationSignal`, `Deprecation\LinkHeader`, `Http\HttpDate` ; le port
  `Deprecation\ObservedDeprecations`.
- La lecture tolérante : `TolerantReading\TolerantEnum`.
- La vérification OpenAPI : `OpenApi\ContractVerifier`, `OpenApiDocument`, `Operation`, `RuleLevel` (minimal,
  recommended, strict), `Severity`, `Finding` et `Report` ; l'interface `Contract\OpenApiRule` ; dix-sept règles dans
  `OpenApi\Rule`, pour un document OpenAPI 3.0, 3.1 ou 3.2 d'une API REST qui parle JSON sans numéros de version. La
  lecture est bornée à 8 Mio, 128 niveaux et 500 000 valeurs ; les constats citent le document gardé imprimable et
  borné.
- Les exceptions : `ApiContractException`, `InvalidContractValue`, `UnknownAuthorityValue`, `UnreadableDocument`.
- La cible de fuzzing `openapi-verifier`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
