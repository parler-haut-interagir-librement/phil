<!-- phil-dev source-sha256=f35bdec1e203cf496587262727a132767ef9220df566ae53cde5ec572739fa54 -->
# phil/url-bundle

**Des champs de lien qui détectent leur genre, une contrainte de validation, un type de colonne et des filtres Twig.**

`phil/url-bundle` intègre `phil/url` à une application Symfony. `TypedUrlType` est un seul champ texte qui lit une
adresse, en `https` par défaut, et détecte son genre parmi les ensembles d'enums qu'il accepte, ce qui donne un
`TypedUrl`. La contrainte `UrlOfType` vérifie qu'un texte, un `Url` ou un `TypedUrl` est d'un genre accepté. Avec
DoctrineBundle, le type de colonne `phil_url` garde un `Url` normalisé dans un `VARCHAR(2048)`. Twig reçoit
`url_display`, `url_type()` et `url_type_label`. Les messages sont fournis en anglais et en français.

Il s'adresse aux applications Symfony dont les formulaires recueillent des liens : un profil social, le site Web d'une
boutique, la page d'un organisme dans un registre. Un champ ou une contrainte accepte les genres internationaux de
`KnownUrlType`, les enums propres à l'application, ou les deux, nommés par une classe d'enum, un cas, un `EnumSet` ou
une liste de ceux-ci. Une adresse et son genre sont stockés dans deux colonnes que l'application mappe elle-même, le
genre avec sa propre enum.

Utilisez `phil/url` directement hors de Symfony ou dans un modèle de domaine. Le bundle n'ajoute aucune règle propre :
la normalisation, les genres et la détection viennent de la bibliothèque, et le bundle les branche sur Symfony Form,
Validator, Doctrine DBAL et Twig.

## État

| Partie | État |
|---|---|
| `TypedUrlType` : un seul champ texte, le genre détecté | Implémenté et testé |
| `UrlOfType` : un texte, un `Url` ou un `TypedUrl` d'un genre accepté | Implémenté et testé |
| Le type de colonne `phil_url`, facultatif | Implémenté et testé |
| Twig : `url_display`, `url_type()`, `url_type_label` | Implémenté et testé |
| Les messages en anglais et en français | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, enregistrement. |
| [Genres acceptés](doc/fr/kinds.md) | Comment un champ ou une contrainte nomme les genres qu'il accepte, ceux de l'application compris. |
| [Formulaires](doc/fr/forms.md) | `TypedUrlType`. |
| [Validation](doc/fr/validation.md) | `UrlOfType`. |
| [Doctrine](doc/fr/doctrine.md) | Le type de colonne `phil_url`, et la façon de garder une adresse avec son genre. |
| [Twig](doc/fr/twig.md) | `url_display`, `url_type()`, `url_type_label`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/url-bundle
```

```php
$builder->add('instagram', TypedUrlType::class, ['types' => KnownUrlType::Instagram, 'required' => false]);

#[UrlOfType([KnownUrlType::Facebook, KnownUrlType::Instagram])]
public ?string $social = null;
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/url` 0.1.3 et `phil/enums` 0.3 ; `symfony/config`, `symfony/dependency-injection`,
`symfony/form`, `symfony/options-resolver` et `symfony/validator` 8.2 ou plus récent. Doctrine DBAL et Twig sont
facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
