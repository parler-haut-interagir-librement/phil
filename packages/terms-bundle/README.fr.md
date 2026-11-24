<!-- phil-dev source-sha256=6adbab5578804f57b27525c9896a9c4c8c1dffc59db963e64e05cecd3f1ed795 -->
# phil/terms-bundle

**Des conditions d'utilisation versionnées pour Symfony : chaque version publiée une seule fois, chaque acceptation
conservée, et un membre sollicité de nouveau quand les conditions changent.**

phil/terms-bundle conserve les politiques qu'un site demande à ses membres d'accepter — ses conditions d'utilisation,
sa politique de confidentialité, son code de conduite — sous forme de versions publiées une seule fois et jamais
modifiées : un numéro, le texte de chaque locale, le moment de sa publication, le moment de son entrée en vigueur, et
s'il modifie les conditions assez pour que chaque membre doive l'accepter de nouveau. Chaque acceptation est un
enregistrement auquel on ne fait jamais qu'ajouter : qui, quelle version, quand, où, et — quand l'application le
veut — le réseau d'où venait la requête, jamais l'adresse —, avec la locale du texte montré et le SHA-256 de son HTML, la
preuve du texte même qui a été accepté. Une porte envoie un membre connecté qui a une version
importante à accepter vers une page où chaque version a sa propre case à cocher, jamais cochée d'avance ; une API
reçoit une réponse `403` avec un problème en JSON. Un formulaire d'inscription obtient la même case à cocher grâce à
une seule option. Une acceptation peut être redemandée après un certain nombre de jours, et des rappels partent selon
un horaire. Une politique peut nommer la licence des contributions des membres, choisie avec chaque version et datée
par son acceptation. Chaque version garde une adresse publique, avec les changements depuis la version précédente ;
une administration les publie, et une commande énumère qui n'a pas accepté.

Il s'adresse à tout site ouvert au public qui doit montrer quelles conditions chaque membre a acceptées, et quand.

## État

| Partie | État |
|---|---|
| Politiques, versions immuables avec leurs locales, leurs moments et leur indicateur de changement important | Implémenté et testé |
| Acceptations, en ajout seulement, avec leur canal et, au besoin, le réseau | Implémenté et testé |
| La locale et le SHA-256 du texte accepté, et la vérification de cette preuve | Implémenté et testé |
| La porte sur chaque requête, la page d'acceptation, le problème d'une API | Implémenté, testé et mesuré au banc d'essai |
| La case à cocher d'inscription, la version affichée vérifiée à la soumission | Implémenté et testé |
| Renouvellement périodique, rappels selon un horaire, avis d'une nouvelle version | Implémenté et testé |
| La licence des contributions, choisie avec une version et datée par son acceptation | Implémenté et testé |
| Pages publiques versionnées et changements entre deux versions | Implémenté et testé |
| L'administration en Twig, en anglais et en français, et le menu EasyAdmin | Implémenté et testé |
| Les pages dessinées par les recettes du kit PHIL UX ou du kit Shadcn, chacun rendu avec les deux dans les tests | Implémenté et testé |
| Les moments gardés en UTC, l'administration les montrant dans son fuseau horaire | Implémenté et testé |
| `phil:terms:outstanding`, `phil:terms:check-legal-links`, l'exportation et l'effacement des acceptations | Implémenté et testé |
| La recette Flex : le bundle, sa configuration et ses routes, et les deux classes qui nomment les membres | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, les tables, la recette, les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_terms`. |
| [Politiques et versions](doc/fr/versions.md) | Les politiques, les versions, les changements importants, l'entrée en vigueur d'une version, les textes. |
| [Acceptation](doc/fr/acceptance.md) | Ce qu'un membre doit accepter, la porte, la page d'acceptation, les API, le formulaire d'inscription, l'enregistrement depuis le code, la preuve du texte accepté. |
| [Renouvellement et rappels](doc/fr/renewal-and-reminders.md) | Les acceptations redemandées, les rappels, les avis d'une nouvelle version. |
| [Licence des contributions](doc/fr/contribution-licence.md) | La licence des contributions des membres, choisie avec une version et datée par son acceptation. |
| [Pages publiques](doc/fr/public-pages.md) | Les adresses versionnées, les changements, le cache, les recettes du kit. |
| [Administration](doc/fr/administration.md) | Publier une version, le rôle, le jeton, la mise en page, EasyAdmin. |
| [Console](doc/fr/console.md) | `phil:terms:outstanding` et `phil:terms:check-legal-links`. |
| [Renseignements personnels](doc/fr/personal-data.md) | Ce que conserve une acceptation, le réseau, l'exportation et l'effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les services et les options qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests, le seuil de mutation et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/terms-bundle
```

```yaml
# config/packages/phil_terms.yaml
phil_terms:
    locales: [en, fr]
    policies:
        terms: ~
        privacy: { renew_after_days: 365 }
    member_resolver: App\Security\MemberId
    member_source: App\Security\EveryMember
    admin: { enabled: true, role: ROLE_LEGAL }
```

```yaml
# config/routes/phil_terms.yaml
phil_terms:
    resource: '@PhilTermsBundle/config/routes/pages.php'
    prefix: /terms
phil_terms_admin:
    resource: '@PhilTermsBundle/config/routes/admin.php'
    prefix: /admin/terms
```

```php
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Domain\AcceptanceChannel;

$terms->publish('terms', ['en' => $english, 'fr' => $french]);
$terms->accept($member->id, 'terms', 1, AcceptanceChannel::Api);
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, Doctrine DBAL 4.5 ou plus récent, Twig 3.21 ou plus récent et
Symfony UX Twig Component 3.7 ou plus récent, avec les recettes du kit PHIL UX — ou du kit Shadcn — installées par le
Symfony UX Toolkit. Doctrine ORM, la console, le composant de formulaires, le planificateur, le traducteur, EasyAdmin,
`phil/ip-anonymizer`, `phil/legal-notices-bundle`, `phil/notifications-bundle`, `phil/privacy-bundle`,
`phil/text-diff` et `phil/ujo` sont facultatifs.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
