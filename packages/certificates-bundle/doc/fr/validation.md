<!-- phil-dev source-sha256=f326d8cb357732a10b1dad79a868f7da4c970fd96b719de5a0cfd8274872ada5 -->
# Validation

Les attestations que porte un certificat sont des `OpenBadgeCredential` d'Open Badges 3.0. Les tests de conformité de
1EdTech sur le bundle sont en cours : cette version n'est pas certifiée par 1EdTech, et ne prétend pas l'être. Les
tests du bundle vérifient chaque attestation avec `phil/open-badges` et contrôlent sa forme hors ligne; le validateur de
conformité de 1EdTech, qui lit les attestations comme le font les portefeuilles et les registres certifiés par 1EdTech,
est la lecture extérieure que prépare cette page, et une application peut l'exécuter elle-même avec la procédure
ci-dessous.

## Vérifier un certificat dès aujourd'hui

| Qui | Comment |
|---|---|
| Quiconque a le papier ou le PDF | Balaie le code QR, ou saisit le code dans le registre : la page dit si le certificat est valide, expiré ou révoqué ([Le registre](register.md)). |
| Quiconque a l'attestation | La colle, ou colle l'attestation extraite des fichiers associés du PDF, dans la page de vérification de `phil/open-badges-bundle`, qui vérifie sa preuve, sa période, son état et, quand elle est donnée, l'identité de son titulaire. |
| L'application | Appelle `CredentialVerifier::verify()` de `phil/open-badges` avec le jeton ou le document, ou lit l'état avec `bin/console phil:certificates:status <code>` ([Commandes de console](console.md)). |
| Un autre système | Lit l'attestation avec tout vérificateur de Verifiable Credentials 2.0 qui prend en charge VC-JWT avec `EdDSA` ou `RS256`, ou la suite cryptographique `eddsa-rdfc-2022` : le profil de l'émetteur, ses clés et ses listes d'état sont des routes publiques de `phil/open-badges-bundle`. |

## Ce que fournit l'application

Les contextes JSON-LD d'Open Badges 3.0 et de CLR 2.0 sont publiés par 1EdTech selon ses propres conditions : le bundle
ne les fournit donc pas. L'application les télécharge une fois, les garde avec sa configuration et les nomme dans
`phil_open_badges.contexts`, que lit la suite Data Integrity.

```yaml
# config/packages/phil_open_badges.yaml
phil_open_badges:
    contexts:
        'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json': '%kernel.project_dir%/config/jsonld/ob-v3p0-context-3.0.3.json'
        'https://purl.imsglobal.org/spec/clr/v2p0/context-2.0.1.json': '%kernel.project_dir%/config/jsonld/clr-v2p0-context-2.0.1.json'
```

Le validateur va aussi chercher ce vers quoi l'attestation pointe : l'application mise à l'essai répond donc à une
adresse HTTPS publique, avec le profil et les clés de l'émetteur, la liste d'état de l'attestation, la page de la
formation de la réalisation et son image.

## Où s'exécute le validateur

Le validateur de conformité est un service que 1EdTech exploite sur son propre site, pour les produits qu'il certifie;
il n'en existe aucune copie à exécuter hors ligne ni en intégration continue. Il lit l'attestation qu'on lui donne et
va chercher, sur l'Internet public, tout ce vers quoi elle pointe. Une exécution exige donc :

- un compte sur le site de conformité et de certification de 1EdTech, où s'ouvrent les tests d'émetteur Open Badges
  3.0;
- l'application déployée sur un hôte HTTPS public dont une autorité publique a signé le certificat, ses routes
  accessibles sans connexion;
- les attestations exportées de la procédure ci-dessous.

Avant cette exécution, les vérifications qui n'exigent aucun réseau sont celles du bundle : chaque attestation est
vérifiée avec `phil/open-badges` dans les tests, sa forme est vérifiée comme le dit la section suivante, le fichier
associé du PDF et son profil PDF/A-3b sont vérifiés contre un vrai Gotenberg par `composer test:services`, et les
commandes ci-dessous donnent les mêmes fichiers que lit le validateur.

## Ce qui est vérifié hors ligne

`tests/Unit/Application/ExportedShapeTest.php` décerne un certificat, exporte son attestation avec
`phil:certificates:export`, une fois comme jeton et une fois comme document avec sa preuve, et vérifie sa forme contre ce
que décrit le texte public d'Open Badges 3.0. Les règles sont écrites dans `tests/Unit/Support/Ob3Shape.php` : aucun
document de contexte ni schéma JSON de 1EdTech n'est lu ni gardé, puisque le bundle ne fournit aucun fichier dont le
titulaire est un tiers.

| Partie | Ce qui est vérifié |
|---|---|
| `@context` | Le contexte de Verifiable Credentials 2.0 en premier, celui d'Open Badges 3.0 parmi les autres |
| `id`, `type`, `name` | Une URI; `VerifiableCredential` et `OpenBadgeCredential`; un nom |
| `issuer` | Un `Profile` avec son `id` |
| `validFrom`, `validUntil` | Des dates et heures avec leur décalage |
| `credentialSubject` | Un `AchievementSubject` avec un `id` ou un `identifier`; chaque `IdentityObject` empreinté en `sha256$` et 64 chiffres hexadécimaux, avec son sel |
| `achievement` | Un `Achievement` avec son `id`, `name`, `description`, `criteria` (un `id` ou un `narrative`) et une `Image` quand il y en a une |
| `credentialStatus` | Un `BitstringStatusListEntry` de révocation ou de suspension, son indice une chaîne de chiffres, sa liste une URI |
| Un jeton | `alg` EdDSA ou RS256, un `kid` ou un `jwk`; `iss`, `jti`, `nbf` et `exp` l'émetteur, l'identifiant, `validFrom` et `validUntil` de l'attestation |
| Un document | Une `DataIntegrityProof` d'une suite cryptographique EdDSA, d'objectif `assertionMethod`, par une clé de l'émetteur, sa valeur en base 58 |

Ce qu'elle ne peut pas savoir, c'est ce que le validateur va aussi chercher : le profil et les clés de l'émetteur tels
qu'un autre site les lit, la liste d'état, la page de la formation et son image, sur l'Internet public. Cette lecture est
l'exécution qui suit.

## La procédure

1. Déployez l'application sur un hôte HTTPS public, avec son émetteur, sa clé et ses contextes configurés, et les routes
   de `phil/open-badges-bundle` et du registre importées.
2. Définissez une formation et décernez un certificat d'essai à une identité qui est la vôtre :

   ```sh
   bin/console phil:certificates:award first-aid "Test Learner" test@example.org --score=100
   ```

3. Exportez son attestation, une fois sécurisée comme un jeton et une fois comme un document avec sa preuve
   (`phil_open_badges.securing` réglé à `jwt`, puis à `data_integrity`, chacun avec son propre certificat) :

   ```sh
   bin/console phil:certificates:export <code> > credential.jwt
   bin/console phil:certificates:export <code> > credential.json
   ```

4. Donnez chaque fichier au validateur, comme l'exige sa documentation : le jeton comme un JWT, le document comme une
   attestation JSON-LD avec une preuve intégrée. Une attestation sécurisée par une clé RSA
   (`phil_open_badges.key.algorithm: RS256`) ne se donne que comme jeton, puisque les preuves Data Integrity sont
   `eddsa-rdfc-2022`.
5. Révoquez un certificat, attendez le `max_age` de la liste d'état, exportez-le de nouveau, et vérifiez que le
   validateur le déclare révoqué.
6. Téléchargez le PDF du certificat depuis le registre, extrayez `credential.jwt` ou `credential.json` de ses fichiers
   associés, et vérifiez que c'est l'attestation exportée, octet pour octet.

## Quoi consigner

Pour chaque exécution : la version du bundle et celle de `phil/open-badges`, la sécurisation, la version du validateur
et son rapport complet. Une constatation que le validateur soulève sur la forme de l'attestation est un défaut de
`phil/open-badges`, signalé là avec le fichier exporté; une constatation soulevée sur une ressource récupérée (le
profil, les clés, une liste d'état) est un défaut de `phil/open-badges-bundle`.
