<!-- phil-dev source-sha256=bab22c7d5af52867f6787fa3f9e349152d3fadde6db1543de5ad8feb7879333d -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `array_find()` et `array_any()` |

La bibliothèque n'a aucune autre dépendance, pas même une extension hors du cœur de PHP.

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
