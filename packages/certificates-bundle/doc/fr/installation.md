<!-- phil-dev source-sha256=9b685642db8280018ada2dc575238376dd0450fdbffb2bea6f7f8bbc8db4c337 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec les extensions `intl` et `sodium`, Symfony 8.2 ou plus récent. Le bundle s'appuie sur deux
autres bundles, que l'application enregistre et configure d'abord :

- `phil/open-badges-bundle`, pour l'émetteur, sa clé et les listes d'état qu'il publie;
- `phil/paged-media-bundle`, pour le PDF, et l'URL de Gotenberg quand l'attestation est intégrée ou le PDF signé.

Les pages du registre exigent TwigBundle; sans lui, les routes n'ont pas de contrôleur et le reste fonctionne. Le
stockage fourni exige DoctrineBundle; sans lui, l'application fournit les dépôts
([Points d'extension](extension-points.md)).

```sh
composer require phil/certificates-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\OpenBadgesBundle\PhilOpenBadgesBundle::class => ['all' => true],
    Phil\PagedMediaBundle\PhilPagedMediaBundle::class => ['all' => true],
    Phil\CertificatesBundle\PhilCertificatesBundle::class => ['all' => true],
];
```

Le conteneur refuse de se construire quand l'un des bundles sur lesquels il s'appuie manque.

## Les listes d'état

Le bundle désigne son propre `CertificateStatusLists` comme les listes que publie `phil/open-badges-bundle`, à moins
que la configuration de l'application nomme un autre service sous `phil_open_badges.status_lists.service`. Une
application qui émet aussi d'autres badges garde ses propres listes et les donne à
`phil_certificates.status_lists.others` ([Révocation](revocation.md)).

## Le schéma

Avec DoctrineBundle, les entités `Course` et `Certificate` sont associées à partir de `src/Domain/Model`, dans les
tables `phil_certificates_course` et `phil_certificates_certificate`. Générez la migration comme pour les entités de
l'application :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sur MySQL et MariaDB, dont les collations par défaut ignorent la casse et les accents, `BinaryKeysListener` associe en
`utf8mb4_bin` les clés que le registre consulte — le code, le titulaire et l'achèvement d'un certificat, le slug et la
source d'achèvement d'une formation, le membre et la source d'une activité d'apprentissage —, de sorte que les membres
`Alice` et `alice` ne lisent jamais les certificats l'un de l'autre. Les identifiants, que les clés étrangères
référencent, gardent la collation de la table. L'écouteur est enregistré avec DoctrineBundle; la migration que
`doctrine:migrations:diff` écrit modifie les colonnes d'un schéma existant.

### Les instants en UTC

Chaque instant que le bundle enregistre est écrit et relu en UTC, quel que soit le fuseau horaire du processus PHP —
`phil_certificates_certificate` : `awarded_at`, `valid_until`, `revoked_at`, `reminded_at` ; `phil_certificates_course` :
`ends_at` ; `phil_certificates_activity` : `completed_at`. Les colonnes sont associées au type `datetime_utc_immutable` et les requêtes qui les comparent passent le même
type : un serveur web, une commande de la console et un worker de Messenger qui tournent dans des fuseaux horaires
différents lisent le même instant, et une expiration ne peut pas se décaler de quelques heures. Le type SQL est celui de
`datetime_immutable` : le schéma ne change donc pas. Un instant relu est en UTC ; affichez-le dans le fuseau horaire du
lecteur, comme le fait le filtre `date` de Twig.

#### Migrer depuis 0.2

Une base écrite par 0.2 garde chaque instant dans le fuseau horaire du processus qui l'a écrit. Quand ce fuseau était
UTC, il n'y a rien à faire. Sinon, convertissez les lignes une seule fois, application arrêtée pour qu'aucune ligne ne
soit écrite sous les deux formes, en donnant le fuseau dans lequel les processus tournaient — `America/Toronto` ici :

```sql
-- MySQL and MariaDB: named zones need the time zone tables (mysql_tzinfo_to_sql).
UPDATE phil_certificates_certificate SET
    awarded_at = CONVERT_TZ(awarded_at, 'America/Toronto', 'UTC'),
    valid_until = CONVERT_TZ(valid_until, 'America/Toronto', 'UTC'),
    revoked_at = CONVERT_TZ(revoked_at, 'America/Toronto', 'UTC'),
    reminded_at = CONVERT_TZ(reminded_at, 'America/Toronto', 'UTC');
UPDATE phil_certificates_course SET
    ends_at = CONVERT_TZ(ends_at, 'America/Toronto', 'UTC');
UPDATE phil_certificates_activity SET
    completed_at = CONVERT_TZ(completed_at, 'America/Toronto', 'UTC');
-- PostgreSQL
UPDATE phil_certificates_certificate SET
    awarded_at = (awarded_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    valid_until = (valid_until AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    revoked_at = (revoked_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    reminded_at = (reminded_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
UPDATE phil_certificates_course SET
    ends_at = (ends_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
UPDATE phil_certificates_activity SET
    completed_at = (completed_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
```

La nuit où l'on recule les horloges, l'heure entre 01 h 00 et 02 h 00 se produit deux fois et une heure locale
enregistrée ne dit pas laquelle c'était : une ligne de cette heure peut finir avec une heure d'écart. Quand les
processus écrivaient dans des fuseaux horaires différents, on ne peut pas distinguer les lignes : vérifiez avec `phil:certificates:status` les fins de validité proches.

## Les routes

```yaml
# config/routes/phil_certificates.yaml
phil_certificates:
    resource: '@PhilCertificatesBundle/config/routes.php'
    prefix: /certificates
```

Importez aussi les routes de `phil/open-badges-bundle`, puisque les attestations pointent vers ses listes d'état.
Chaque route du registre est publique : gardez-les hors d'un pare-feu qui demande une connexion. Réglez
`framework.router.default_uri` à l'adresse du site, pour que les URL écrites dans les attestations et imprimées sur les
certificats décernés depuis une commande ou un *worker* soient celles que sert le site.
