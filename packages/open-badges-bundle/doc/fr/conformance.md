<!-- phil-dev source-sha256=613af59a9126c217557b769dc78dcf7a552dbd0c3942f508f8f7a79ead336788 -->
<!-- phil-dev source-sha256=x -->
# Conformité

Les attestations que le bundle émet sont des `OpenBadgeCredential` d'Open Badges 3.0, sécurisées comme des jetons Web
JSON de Verifiable Credentials 2.0 ou avec des preuves Data Integrity `eddsa-rdfc-2022`. Les tests de conformité de
1EdTech sur le bundle sont en cours : cette version n'est pas certifiée par 1EdTech, et ne prétend pas l'être. Tant que
ces tests ne sont pas terminés, une application qui s'appuie sur les attestations devant d'autres systèmes les vérifie
comme le décrit cette page, et peut exécuter elle-même le validateur de conformité.

## Ce qui est testé aujourd'hui

| Partie | Comment |
|---|---|
| Le traitement JSON-LD, la canonicalisation et les preuves `eddsa-rdfc-2022` | `phil/open-badges` reproduit le vecteur d'essai du W3C de la suite cryptographique et réussit tous les tests d'évaluation de la suite RDF Dataset Canonicalization du W3C. |
| Les jetons | `phil/open-badges` vérifie chaque jeton avec l'algorithme de la clé, jamais celui du jeton, et contrôle ses revendications enregistrées contre l'attestation. |
| Les attestations émises | Les attestations qu'émettent les tests du bundle sont vérifiées de nouveau avec `CredentialVerifier` : comme jetons, comme documents avec leur preuve, et intégrées dans des images PNG et SVG par la page de vérification. |
| Les documents publiés | Les tests lisent le profil de l'émetteur, son JSON Web Key Set et ses listes d'état depuis les routes, et contrôlent les clés et les bits qu'ils publient. |
| Les clés d'autres émetteurs | Des profils et des documents `did:web` lus avec des clés Ed25519 et RSA, chaque refus testé, et le lecteur fuzzé ([Autres émetteurs](remote-issuers.md)). |
| La forme d'une attestation | `phil/certificates-bundle` contrôle hors ligne la forme des attestations qu'il exporte contre ce que décrit Open Badges 3.0. |

## Vérifier une attestation dès aujourd'hui

Chacun de ces moyens lit une attestation que le bundle a émise; les deux derniers n'exigent rien du bundle :

1. **La page de vérification.** Importez `config/verification_routes.php` et ouvrez `/verify` sous son préfixe : elle
   vérifie un jeton ou un document collé, ou un PNG ou un SVG intégrant l'attestation, pour sa preuve, sa période, son
   état et, quand elle est donnée, l'identité de son destinataire ([Page de vérification](verification-page.md)).
2. **Le service `CredentialVerifier`.** Dans l'application, injectez `Phil\OpenBadges\Verification\CredentialVerifier`
   et appelez `verify()` avec le jeton ou le document; une attestation qui ne tient pas lève `InvalidCredential` avec sa
   raison.

   ```php
   $verified = $verifier->verify($credential);    // a VerifiedCredential, or InvalidCredential
   $verified->issuer();
   ```

3. **Un autre vérificateur.** Le profil de l'émetteur à son identifiant publie sa clé comme un `Multikey`, et
   `/issuer/keys` comme un JSON Web Key Set; un jeton porte son `kid` et son `jwk`. Un vérificateur de Verifiable
   Credentials 2.0 qui prend en charge VC-JWT avec `EdDSA` ou `RS256`, ou la suite cryptographique `eddsa-rdfc-2022`, les
   lit sans aucune configuration de ce bundle. Les listes d'état sont des `BitstringStatusListCredential` à
   `/status/{list}`.
4. **Le validateur de conformité.** 1EdTech exécute son validateur sur son propre site, pour un compte ouvert là, contre
   un émetteur sur un hôte HTTPS public. `phil/certificates-bundle` écrit la procédure (déployer, décerner, exporter,
   donner les fichiers au validateur, révoquer et vérifier de nouveau) dans son `doc/validation.md`; elle vaut pour
   toute attestation que ce bundle sécurise.

## Les contextes JSON-LD

Les contextes d'Open Badges 3.0 et de CLR 2.0 sont publiés par 1EdTech selon ses propres conditions : le bundle ne les
fournit donc pas. L'application les télécharge une fois et les nomme dans `contexts` ([Configuration](configuration.md)).
Les preuves Data Integrity en ont besoin; les jetons, non.

## Signaler une constatation

Un vérificateur ou le validateur qui refuse une attestation que le bundle a émise est un défaut à signaler, avec
l'attestation, la version du bundle et de `phil/open-badges`, la sécurisation, et le rapport complet du vérificateur.
Une constatation sur l'attestation elle-même (sa forme, sa preuve, son jeton) relève de `phil/open-badges`; une
constatation sur un document que servent les routes (le profil, les clés, une liste d'état) relève de ce bundle.
