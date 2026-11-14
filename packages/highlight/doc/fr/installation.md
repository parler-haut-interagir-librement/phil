<!-- phil-dev source-sha256=05c4e64dd23730f74980fe6b3826020be1a85cfe436c365c90925d467a3eee04 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `array_find()` et `array_any()` |

La bibliothèque n'a aucune autre dépendance, pas même une extension hors du cœur de PHP.

## Extensions PHP

Les analyseurs lexicaux sont en PHP pur, et le surligneur n'emploie aucune extension, même installée. Les extensions de
coloration syntaxique installables par PIE ne conviennent pas :

| Extension | Pourquoi elle n'est pas employée |
|---|---|
| `yiipress/highlighter` (syntect) | Elle renvoie du HTML à styles en ligne plutôt que des jetons : ni les classes `hl-`, ni les thèmes, ni les annotations de lignes ne s'appliqueraient ; elle n'a pas de grammaire Twig ; et elle est plus lente que ces analyseurs sur PHP, JavaScript, HTML, CSS, YAML et SQL. |
| `xberg-io/tree-sitter-language-pack` | Elle télécharge chaque grammaire du réseau à la première utilisation, ce qu'un serveur ne doit jamais faire ; elle ne publie aucune version à fils d'exécution sûrs, que FrankenPHP exige ; et son API PHP n'exécute aucune requête de coloration. |

## Installer

```sh
composer require phil/highlight
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `Highlighter` là où le code est
affiché, une seule fois, et gardez-le : `Highlighter::default()` construit chaque analyseur lexical, ce qui coûte peu,
et aucun ne garde d'état entre deux sources.

## Les thèmes

Le HTML ne contient que des classes ; un thème les colore. Trois sont fournis dans `themes/` :

| Fichier | Couleurs |
|---|---|
| `themes/light.css` | Pour un fond clair |
| `themes/dark.css` | Pour un fond sombre |
| `themes/auto.css` | Clair ou sombre selon la préférence du système, sauf si un ancêtre porte `data-theme="light"` ou `data-theme="dark"` |

Servez celui que vous voulez depuis `vendor/phil/highlight/themes/`, copiez-le dans les ressources de l'application,
ou écrivez le vôtre ([html-and-themes.md](html-and-themes.md)). La feuille de style de `phil/ux-bundle` colore les
mêmes classes avec ses propres rôles de couleur : une application qui la charge n'a besoin d'aucun thème d'ici.
