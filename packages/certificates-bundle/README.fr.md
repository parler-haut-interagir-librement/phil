<!-- phil-dev source-sha256=5b37afe435c007c4ec6e86675fcdb71b11c4c78abcf5d0bb91dfbad9f5acb541 -->
# phil/certificates-bundle

**Des certificats de réussite qu'une application Symfony décerne, imprime et laisse vérifier par n'importe qui : des
formations et leurs critères de réussite, un PDF avec un code QR et l'attestation signée à l'intérieur, un registre
public, la révocation, les rappels d'expiration, la recertification et les crédits de formation continue.**

phil/certificates-bundle s'appuie sur [`phil/open-badges-bundle`](../open-badges-bundle/README.md) et
[`phil/paged-media-bundle`][paged-media-bundle]. L'application définit ses formations : ce que chacune
reconnaît, le score, les minutes de formation et les modules qu'il faut pour réussir, et la durée de validité d'un
certificat. Elle décerne un certificat quand les acquis d'une personne apprenante y satisfont. Chaque certificat porte
une attestation Open Badges 3.0 signée par l'émetteur, un code pour le registre et un indice dans les listes d'état de
l'émetteur.

Son PDF est dessiné sur une page à l'italienne, avec un code QR qui ouvre la page du certificat dans le registre et
l'attestation signée intégrée comme fichier associé : le fichier fait ainsi sa propre preuve. Le registre montre
n'importe quel certificat par son code (valide, expiré ou révoqué) et, pour chaque formation, les certificats que leurs
titulaires ont choisi d'afficher. Révoquer un certificat active son bit dans la liste d'état que publie l'émetteur :
les vérificateurs d'ailleurs l'apprennent aussi.

Un certificat qui prend fin est expiré, jamais révoqué. Avant sa fin, un événement avertit l'application de rappeler
son titulaire; celui-ci est alors recertifié, avec les crédits de formation continue accumulés entre-temps ou de
nouveaux acquis, et reçoit un nouveau certificat lié à celui qu'il renouvelle.

## État

| Partie | État |
|---|---|
| Formations, critères de réussite (score, minutes, modules), validité en mois, retrait, fin d'une formation | Implémenté et testé |
| Attribution : critères vérifiés, attestation Open Badges signée, destinataire gardé sous forme d'empreinte salée | Implémenté et testé |
| Le PDF : dessin natif, code QR, attestation intégrée par Gotenberg comme fichier associé PDF/A-3, PDF/A-3b et signature facultatifs | Implémenté et testé |
| Le registre : recherche par code, pages de certificat, pages de formation avec les certificats affichés, téléchargements du PDF et de l'attestation | Implémenté et testé |
| Révocation par des Bitstring Status Lists, scindées en listes de taille fixe | Implémenté et testé |
| Association Doctrine et dépôts | Implémenté et testé |
| Attributions sous un verrou de `symfony/lock`, reprises quand le stockage trouve l'indice pris; les événements `CertificateAwarded` et `CertificateRevoked` | Implémenté et testé |
| Expiré distinct de révoqué; rappels avant la fin par `CertificateExpiring`, une fois par seuil | Implémenté et testé |
| Recertification : un nouveau certificat lié à celui qu'il renouvelle, par des crédits de formation continue ou des acquis | Implémenté et testé |
| Crédits de formation continue par membre et par période, au profit d'une formation, consignés une fois par source | Implémenté et testé |
| Commandes de console : définir une formation, décerner, recertifier, révoquer, état, export d'une attestation, rappels, crédits | Implémenté et testé |
| Le code QR du PDF décodé jusqu'à l'adresse du certificat | Testé |
| La forme des attestations exportées comparée à ce que décrit Open Badges 3.0, hors ligne | Testé |
| Tests de conformité de 1EdTech sur les attestations émises | En cours : cette version n'est pas certifiée; la procédure et l'export sont prêts, et [Validation](doc/fr/validation.md) explique comment vérifier un certificat dès aujourd'hui |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, les bundles sur lesquels il s'appuie, le schéma, les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec sa valeur par défaut. |
| [Formations et attribution](doc/fr/courses-and-awarding.md) | Définir des formations et leurs critères, décerner un certificat. |
| [Le PDF](doc/fr/pdf.md) | Ce que montre le certificat, les polices, l'attestation intégrée, le PDF d'archivage, la signature. |
| [Le registre](doc/fr/register.md) | Les pages et les téléchargements que servent les routes, et ce qu'ils divulguent. |
| [Révocation](doc/fr/revocation.md) | Révoquer un certificat, les listes d'état. |
| [Expiration et recertification](doc/fr/recertification.md) | La fin des certificats et des formations, les rappels, la recertification, les crédits de formation continue. |
| [Commandes de console](doc/fr/console.md) | Définir des formations, décerner, recertifier, révoquer, afficher, exporter, rappeler et créditer depuis la console. |
| [Validation](doc/fr/validation.md) | Où en sont les tests de conformité de 1EdTech, comment vérifier un certificat dès aujourd'hui, ce qui est vérifié hors ligne, et l'exécution du validateur de 1EdTech. |
| [Points d'extension](doc/fr/extension-points.md) | Les dépôts, les URL, les événements, le sujet d'impression, la mise en page, les traductions, d'autres listes d'état. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et le test d'une application qui décerne des certificats. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/certificates-bundle
```

```yaml
# config/routes/phil_certificates.yaml
phil_certificates:
    resource: '@PhilCertificatesBundle/config/routes.php'
    prefix: /certificates
```

```php
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;

$courses->define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more on the final assessment.', new PassCriteria(minimumScore: 70), validityMonths: 24);

$certificate = $awarding->award('first-aid', 'Ada Lovelace', 'ada@example.org', new Attainment(score: 84), listed: true);
// https://<site>/certificates/<code>, its PDF at /certificates/<code>/certificate.pdf
```

## Conformité

Les tests de conformité de 1EdTech sont en cours, et cette version n'est pas certifiée par 1EdTech. Un certificat se
vérifie dès aujourd'hui dans le registre par son code, par la page de vérification de `phil/open-badges-bundle`, par
tout vérificateur de Verifiable Credentials 2.0 qui lit VC-JWT ou `eddsa-rdfc-2022`, ou en exécutant le validateur de
1EdTech contre un émetteur déployé; voir [Validation](doc/fr/validation.md).

## Prérequis

PHP 8.5 ou plus récent avec `intl` et `sodium`, Symfony 8.2 ou plus récent, et `phil/open-badges-bundle` et
`phil/paged-media-bundle` enregistrés. L'intégration de l'attestation dans le PDF exige Gotenberg; le registre exige
TwigBundle; le stockage fourni exige DoctrineBundle; les commandes exigent le composant Console; le verrou de l'indice
d'état exige `symfony/lock`.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

[paged-media-bundle]: https://github.com/parler-haut-interagir-librement/paged-media-bundle
