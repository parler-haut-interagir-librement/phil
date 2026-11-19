<!-- phil-dev source-sha256=4c9769fe11754dfb54eed6ed01e2180a7bb54c5c408af988b7817ee1f95da908 -->
# phil/short-link-bundle

**Liens courts pour Symfony sur des domaines dédiés : des codes au format Sqids, des dates de validité, un nombre
maximal de visites, un code QR pour chaque lien, et des visites de personnes comptées à part des robots et des aperçus de liens, sans aucune
donnée sur le visiteur.**

phil/short-link-bundle répond sur un domaine court bien à lui, comme `go.example.com`, avec un domaine par locale quand
un site en a besoin. Chaque lien a un code — tiré au hasard et écrit au format Sqids, ou choisi —, une URL cible, un
statut de redirection et des conditions facultatives : un début, une fin, un nombre maximal de visites. Une visite est
comptée dans l'instruction même qui vérifie les conditions, de sorte que deux requêtes en course pour la dernière
visite ne la comptent qu'une fois. Les robots, les prélectures et les aperçus de liens des services de messagerie sont
redirigés mais comptés à part : ils n'entament jamais un maximum. Un lien qui n'est plus en vigueur répond
`410 Gone` ; un code que personne ne détient répond `404`. L'emplacement est écrit par le résolveur de phil/redirects-bundle : un lien court mène donc où
mènerait une redirection, et il est vérifié de la même façon.

Les cibles sont des URL absolues lues par phil/url, limitées aux hôtes que l'application énumère, jamais sur un domaine
de lien court, et lues au besoin par phil/safe-http avant d'être enregistrées. La création de liens a une limite de
débit par auteur. Rien du visiteur n'est conservé : un lien garde ses comptes et le moment de sa dernière visite — le
`User-Agent` est lu pour classer une visite, puis abandonné —, et les enregistreurs informés d'une visite — dont les
événements serveur de phil/analytics-bundle — ne reçoivent que des identifiants.

## État

| Partie | État |
|---|---|
| Codes au format Sqids, alphabet et liste de blocage configurables | Implémenté, testé et soumis au fuzzing |
| Liens, leurs conditions et leur dépôt Doctrine, sur SQLite, MySQL et PostgreSQL | Implémenté et testé |
| Domaines de liens courts servis avant le routage, `404` et `410` | Implémenté et testé |
| Compte atomique des visites, requêtes en course comprises | Implémenté et testé sur MySQL et PostgreSQL |
| Vérification des cibles, limite de débit à la création | Implémenté et testé |
| L'administration, son formulaire, ses codes QR en SVG et en PNG | Implémenté et testé |
| Visites des personnes et des machines comptées à part, classeur remplaçable | Implémenté, testé et soumis au fuzzing |
| Enregistreurs de visites et enregistreur de la mesure d'audience | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, la table, les routes, le DNS d'un domaine court. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_short_link`. |
| [Codes](doc/fr/codes.md) | Le format Sqids, l'alphabet, la liste de blocage, les codes tirés et les codes choisis. |
| [Résolution](doc/fr/resolution.md) | Ce que répond une requête sur un domaine court, les domaines des locales, le compte des visites. |
| [Sécurité et vie privée](doc/fr/safety.md) | Les cibles, les redirections ouvertes, la limite de débit, ce qui est conservé et ce qui ne l'est pas. |
| [Administration](doc/fr/administration.md) | Les pages, le rôle, le jeton CSRF, la mise en page, les codes QR. |
| [Points d'extension](doc/fr/extension-points.md) | Les enregistreurs, les vérifications, les dépôts et les services qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests, les serveurs qu'ils lisent et la cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/short-link-bundle
```

```yaml
# config/packages/phil_short_link.yaml
phil_short_link:
    domains:
        - { host: go.example.com }
        - { host: lien.example.fr, locale: fr }
    home_url: 'https://www.example.com/'
    targets:
        allowed_hosts: [www.example.com, www.example.fr]
```

```yaml
# config/routes/phil_short_link.yaml
phil_short_link_admin:
    resource: '@PhilShortLinkBundle/config/routes/admin.php'
    prefix: /admin/short-links
```

```php
use Phil\ShortLinkBundle\Application\ShortLinkDraft;
use Phil\ShortLinkBundle\Application\ShortLinks;

$draft = new ShortLinkDraft();
$draft->target = 'https://www.example.com/festival/2027';
$draft->maxVisits = 500;

$link = $shortLinks->create($draft, 'user:'.$user->getUserIdentifier());
$shortLinks->url($link); // https://go.example.com/Xk3mQp
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine ORM 3.7 et DBAL 4.5 ou une
version plus récente, et `phil/url` 0.1.3, qui exige les extensions intl, mbstring et URI. L'administration a besoin de
Twig, des formulaires, du traducteur, de la sécurité et de ses jetons CSRF ; la vérification d'une cible a besoin de
phil/safe-http ; l'enregistreur de la mesure d'audience a besoin de phil/analytics-bundle.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
