<!-- phil-dev source-sha256=7bf676a7f2d1a9bceb814a80443b34c02f94461317a55b36f81d9feb12cccb4a -->
# phil/ux-easyadmin-bundle

**L'administration EasyAdmin aux couleurs de PHIL UX, en clair et en sombre, sans restyler EasyAdmin.**

Les sites construits sur les bundles PHIL utilisent EasyAdmin comme administration. phil/ux-easyadmin-bundle donne à
cette administration l'apparence de PHIL UX : les variables propres à EasyAdmin prennent les rôles de couleur, les
coins, la largeur du focus et les polices de phil/ux-css, si bien que l'accent, les surfaces, la barre latérale, les
tableaux, les formulaires, les alertes et les badges suivent le système de design du site, dans le schéma clair, le
schéma sombre et le schéma automatique.

Il change des valeurs, pas le balisage. Le thème est une feuille de style dans les couches de cascade d'EasyAdmin : les
jetons de phil/ux-css se placent sous la couche `vendor` d'EasyAdmin, où ils perdent contre chaque style de
l'administration, et le thème est une couche déclarée après celles d'EasyAdmin, où il l'emporte sur les jetons
d'EasyAdmin et perd contre les styles propres à l'application. Les règles d'éléments de phil/ux-css, sur `button`,
`input` ou `code`, sont écartées, si bien que l'administration garde les composants d'EasyAdmin. Aucun gabarit
d'EasyAdmin n'est remplacé.

Le bundle lie la feuille de style sur chaque page de chaque tableau de bord, après les feuilles de style propres au
tableau de bord ; une clé laisse ce soin à chaque tableau de bord.

## État

| Partie | État |
|---|---|
| Le thème : les variables d'EasyAdmin sur les jetons de phil/ux-css, dans les couches de cascade d'EasyAdmin, en clair et en sombre | Implémenté et testé |
| Le thème vérifié contre EasyAdmin 5.10 : chaque variable qu'il pose est déclarée par EasyAdmin, ses couleurs calculées par un vrai navigateur | Implémenté et testé |
| La feuille de style associée sous `phil-ux-easyadmin/` dans AssetMapper | Implémenté et testé |
| La feuille de style liée sur chaque page de chaque tableau de bord, et `theme: false` | Implémenté et testé |
| La recette Flex : le bundle enregistré, sa configuration écrite | Implémentée et testée |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, pourquoi les sites des bundles de PHIL ont EasyAdmin, l'enregistrement, la recette, la configuration, `configureAssets()`. |
| [Le thème](doc/fr/theme.md) | Les couches, les variables, le mode sombre, ce que le thème laisse à EasyAdmin, le changer. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/ux-easyadmin-bundle
```

Chaque tableau de bord de l'application s'affiche maintenant aux couleurs de PHIL UX. Un tableau de bord change une
valeur dans sa propre feuille de style, écrite hors de toute couche :

```css
/* assets/styles/admin.css, listed by the dashboard's configureAssets() */
:root, .ea-dark-scheme {
    --phil-color-brand-fill-loud: light-dark(oklch(0.45 0.15 150), oklch(0.75 0.12 150));
}
```

## Prérequis

PHP 8.5 ou plus récent ; EasyAdmin 5.10 ; AssetMapper, la configuration, l'injection de dépendances et le noyau HTTP de
Symfony 8.2 ; phil/ux-css 0.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
