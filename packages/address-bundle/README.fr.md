<!-- phil-dev source-sha256=0d799e98bab4a6c3f4879ab3937ff089dffad05895d8aba92519442dd43483ce -->
# phil/address-bundle

**Un formulaire d'adresse pour Symfony dont les champs suivent le pays choisi.**

phil/address-bundle apporte les adresses de `phil/address` dans Symfony. `AddressType` affiche les champs du format du
pays choisi, dans son ordre, nommés comme il les nomme et exigés comme il les exige. Lorsque le pays a des États ou des
provinces, la région administrative est un choix parmi eux. Quand le pays change, un contrôleur Stimulus lit le point de
terminaison des formats du bundle, puis affiche, masque et renomme les champs dans le navigateur. À la soumission,
l'adresse est vérifiée par rapport à son pays, et chaque champ erroné reçoit sa propre erreur. Le bundle fournit aussi
la contrainte `ValidAddress`, le type de colonne Doctrine `phil_address` pour une seule colonne JSON, les filtres Twig
`address`, `address_lines` et `address_label`, et les fonctions Twig `country_name` et `address_schema`. Les libellés et
les messages sont en anglais et en français.

Il s'adresse aux applications Symfony où un client, un membre ou un rédacteur saisit une adresse qui peut se trouver
dans n'importe quel pays, et où des pages ou des étiquettes postales la réaffichent. Des options limitent les pays
offerts et ajoutent les noms du destinataire.

Hors de Symfony, utilisez `phil/address` seul. Le bundle laisse les règles dans la bibliothèque. Le formulaire et la
contrainte interrogent son validateur, le point de terminaison lit ses formats, le type de colonne utilise
`AddressArray`, et Twig son outil de mise en forme.

## État

| Partie | État |
|---|---|
| `AddressType` : champs du pays, États sous forme de choix, erreurs par champ | Implémenté et testé |
| Le point de terminaison des formats et le contrôleur Stimulus `address` | Implémenté et testé |
| `ValidAddress`, le type de colonne `phil_address` | Implémenté et testé |
| Twig : `address`, `address_lines`, `address_label`, `country_name`, `address_schema` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, configuration, la route. |
| [Formulaires](doc/fr/forms.md) | `AddressType`, ses options et son contrôleur Stimulus. |
| [Validation et stockage](doc/fr/validation-and-storage.md) | `ValidAddress` et `phil_address`. |
| [Twig](doc/fr/twig.md) | Les filtres et les fonctions. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/address-bundle
```

```yaml
phil_address:
    default_country: CA
    format_url: /address-format/__COUNTRY__
```

```php
$builder->add('address', AddressType::class);
```

```twig
{{ shop.address|address|nl2br }}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl` ; `phil/address` 0.1 ; les composants config, dependency injection, form, HTTP
foundation, Intl, options resolver et validator de Symfony 8.2, et les contrats de traduction. Doctrine DBAL, le routeur
et Twig sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
