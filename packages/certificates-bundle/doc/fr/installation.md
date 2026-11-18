<!-- phil-dev source-sha256=f59e10c95433ff0dd942369f1090e98b9d9e9a13aff74b8ccfaceff31229b697 -->
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
