<!-- phil-dev source-sha256=0462bd5d8cf4adc7ba02d44ccc8edbdf7c1a73fa92204331c68b9ba3842402cf -->
# phil/ux-bundle

**PHIL UX dans une application Symfony : chaque formulaire à l'apparence de PHIL UX, et la feuille de style servie par AssetMapper.**

phil/ux-bundle relie PHIL UX à Symfony. Il fait deux choses :

- **Le thème de formulaire PHIL UX.** Chaque formulaire produit le rendu de ses rangées comme les champs du kit PHIL UX :
  une rangée est un `isField`, les choix étendus et les sous-formulaires sont un `isFieldSet` nommé par une légende,
  l'aide et les erreurs sont liées au contrôle. Le bundle ajoute le thème à `twig.form_themes` comme Symfony ajoute son
  propre thème par défaut, si bien que rien ne s'écrit dans un gabarit ; les thèmes que l'application liste viennent
  après lui et l'emportent. Une clé le désactive.
- **La feuille de style de phil/ux-css.** Avec AssetMapper, le bundle associe le répertoire `dist/` de phil/ux-css sous
  `phil-ux/`, si bien que `asset('phil-ux/phil-ux.css')` sert le cadriciel et que la table d'importation peut le lister.

L'apparence elle-même vit dans les paquets publics de PHIL UX : phil/ux-css, la feuille de style, et phil/ux-kit, le kit
de composants Twig que le Symfony UX Toolkit installe avec `ux:install`. Le bundle n'installe aucun composant et ne
copie aucun fichier.

Les sites construits sur les bundles PHIL utilisent EasyAdmin comme administration. EasyAdmin produit le rendu de ses
propres formulaires avec son propre thème, si bien que le thème de formulaire PHIL UX ne change pas l'administration ;
`phil/ux-easyadmin-bundle` donne à l'administration les couleurs de PHIL UX.

## État

| Partie | État |
|---|---|
| Le thème de formulaire PHIL UX, ajouté en tête de `twig.form_themes`, et `form_theme: false` | Implémenté et testé |
| La feuille de style de phil/ux-css associée sous `phil-ux/` dans AssetMapper | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la configuration, la feuille de style. |
| [Le thème de formulaire](doc/fr/form-theme.md) | Le balisage de chaque rangée, l'ordre des thèmes, le désactiver. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/ux-bundle
```

```twig
<link rel="stylesheet" href="{{ asset('phil-ux/phil-ux.css') }}">

{{ form(form) }}   {# rendered with the PHIL UX form theme #}
```

## Prérequis

PHP 8.5 ou plus récent ; l'injection de dépendances, la configuration, les formulaires, le noyau HTTP et le bundle Twig
de Symfony 8.2 ; Twig 3.30 ; phil/ux-css 0.5. AssetMapper est facultatif.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
