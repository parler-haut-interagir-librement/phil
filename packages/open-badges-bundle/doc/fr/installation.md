<!-- phil-dev source-sha256=bac4e1547984aa9e804a75447b2cc4555e9a8c07f649dce9639c29808389bfe2 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, `phil/open-badges` 0.1.1 ou plus récent, `phil/url`,
`phil/safe-http` et les contrats de cache de Symfony. La page de vérification exige TwigBundle; sans lui, la page n'est
pas enregistrée et le reste fonctionne. Une limite sur ses vérifications exige `phil/rate-limit-bundle`.

Le bundle signe comme un émetteur que l'application nomme : enregistré sans `issuer.id`, `issuer.name` et `key.secret`,
il arrête la construction et dit lesquels manquent.

```sh
composer require phil/open-badges-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\OpenBadgesBundle\PhilOpenBadgesBundle::class => ['all' => true],
];
```

## La clé

L'émetteur signe avec une seule clé, conservée comme un secret :

```sh
php -r 'echo base64_encode(random_bytes(32)), "\n";'      # an Ed25519 seed
bin/console secrets:set OPEN_BADGES_SEED
```

```yaml
phil_open_badges:
    key:
        secret: '%env(base64:OPEN_BADGES_SEED)%'
```

Une clé RSA de 2048 bits ou plus, en PEM, signe les jetons Web JSON avec `RS256` :
`{algorithm: RS256, secret: '%env(file:OPEN_BADGES_KEY)%', passphrase: '%env(OPEN_BADGES_PASSPHRASE)%'}`. Les preuves
Data Integrity exigent une clé Ed25519.

La clé est lue quand un service en a besoin pour la première fois, pas à la construction du conteneur : le secret
reste une variable d'environnement jusque-là. Changer de clé n'invalide rien de ce qui a déjà été émis, mais les
vérificateurs ont besoin de l'ancienne clé publique pour contrôler les anciennes attestations : gardez les attestations
de l'ancienne clé vérifiables avec `trusted_keys`.

## Les routes

```yaml
# config/routes/phil_open_badges.yaml
phil_open_badges:
    resource: '@PhilOpenBadgesBundle/config/routes.php'
    prefix: /open-badges
phil_open_badges_verification:
    resource: '@PhilOpenBadgesBundle/config/verification_routes.php'
    prefix: /open-badges
```

## Les contextes

Les contextes JSON-LD d'Open Badges 3.0 et de CLR 2.0 sont publiés par 1EdTech selon ses propres conditions.
L'application télécharge une fois ceux qu'elle utilise, les garde avec sa configuration et les nomme dans `contexts` :

```yaml
phil_open_badges:
    contexts:
        'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json': '%kernel.project_dir%/config/jsonld/ob-v3p0-context-3.0.3.json'
        'https://purl.imsglobal.org/spec/clr/v2p0/context-2.0.1.json': '%kernel.project_dir%/config/jsonld/clr-v2p0-context-2.0.1.json'
```

Chaque contexte est téléchargé de sa propre URL, là où 1EdTech le publie, et versionné avec l'application :

```sh
mkdir -p config/jsonld
curl --fail --location -o config/jsonld/ob-v3p0-context-3.0.3.json https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json
curl --fail --location -o config/jsonld/clr-v2p0-context-2.0.1.json https://purl.imsglobal.org/spec/clr/v2p0/context-2.0.1.json
```

Les fichiers sont lus à la première utilisation de la suite Data Integrity, par le `ContextLoader` de
`phil/open-badges` : chacun est un objet JSON avec un `@context`, d'au plus 1 Mio, sinon cette première utilisation
échoue avec une erreur qui nomme le fichier. Un contexte nommé ici est utilisé à la place de tout contexte que porte
`phil/open-badges`; le contexte de Verifiable Credentials 2.0 vient avec `phil/open-badges`. Une URL que ni l'un ni
l'autre ne donne est refusée : rien n'est donc récupéré pendant qu'une preuve est faite ou vérifiée. La sécurisation par
jetons Web JSON ne lit aucun contexte.

## L'identifiant de l'émetteur

L'identifiant de l'émetteur est l'URL absolue de son profil : avec le préfixe `/open-badges`, c'est
`https://<site>/open-badges/issuer`. Les routes sont publiques : gardez-les hors de tout pare-feu qui exige une
connexion.

Réglez `framework.router.default_uri` sur l'adresse du site, pour que les URL des listes d'état écrites dans les
attestations émises par une commande ou un *worker* soient celles que sert le site.
