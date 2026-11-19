<!-- phil-dev source-sha256=c00e20a7e517ae3c174cf8b042d3becbc55038e69f7264e6511f05dff4089101 -->
# Installation

```sh
composer require phil/short-link-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php`, à moins que Symfony Flex ne l'ait fait :

```php
Phil\ShortLinkBundle\PhilShortLinkBundle::class => ['all' => true],
```

phil/redirects-bundle est installé avec lui, pour son résolveur ; il n'a pas besoin d'être enregistré.

## Les domaines

Chaque domaine de lien court pointe vers l'application, comme ses autres hôtes : un enregistrement DNS, un certificat,
et l'hôte parmi ceux que le serveur Web envoie à Symfony. Le bundle répond à une requête sur l'un de ces hôtes avant le
routage, de sorte que les routes du site ne la voient jamais ; une requête sur tout autre hôte suit son cours sans être
touchée.

```yaml
# config/packages/phil_short_link.yaml
phil_short_link:
    domains:
        - { host: go.example.com }
        - { host: lien.example.fr, locale: fr }
```

Quand l'application énumère ses hôtes de confiance (`framework.trusted_hosts`), les domaines de liens courts
appartiennent à cette liste.

## La table

Les liens vivent dans une seule table, `phil_short_link`, mappée par le bundle pour Doctrine ORM. Créez-la avec une
migration :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Sur MySQL, dont l'interclassement par défaut compare le texte sans tenir compte de la casse, deux codes qui ne
diffèrent que par leur casse n'en font qu'un : l'index unique refuse le second, et le bundle tire un autre code ou
refuse celui qui a été choisi. Sur PostgreSQL et SQLite, ce sont deux codes.

## L'administration

L'administration est montée par l'application, sous un préfixe de son choix, derrière son pare-feu :

```yaml
# config/routes/phil_short_link.yaml
phil_short_link_admin:
    resource: '@PhilShortLinkBundle/config/routes/admin.php'
    prefix: /admin/short-links
```

Elle a besoin de `twig/twig`, de `symfony/form`, de `symfony/security-csrf` et du traducteur. Sans eux, mettez
`admin.enabled` à `false`.

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine ORM 3.7 et DBAL 4.5 ou une
version plus récente. Sur PHP 8.5, `SortDirection` vient de `symfony/polyfill-php86`, installé avec le bundle.
