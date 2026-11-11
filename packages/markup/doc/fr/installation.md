<!-- phil-dev source-sha256=115281d354756bf8b3e27e57641d867fcfa52c233cbdde49ffa2a920d5219327 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, enums, constantes typées |
| `ext-ctype` | L'analyseur classe les caractères autour d'un délimiteur |
| `ext-dom` | Le lecteur HTML analyse le HTML avec `Dom\HTMLDocument`, l'analyseur HTML5 de PHP |
| `ext-filter` | L'adresse d'une balise BBCode `[email]` et d'un lien automatique Markdown est validée |
| `ext-libxml` | L'indicateur qui empêche l'analyseur HTML5 de signaler les erreurs du HTML qu'il lit |
| `ext-mbstring` | Les libellés, les ancres et les titres sont coupés et mis en minuscules par caractère |
| `phil/ujo` | Les documents qu'écrivent les lecteurs, leur vérification et leur HTML |
| `symfony/html-sanitizer` 8.2 | Les politiques de `SanitizerPurifier` |
| `symfony/yaml` 8.2 | Le préambule de Markdown, et le YAML qu'un dialecte lit avec `FrontMatter::yaml()` |

Doctrine ORM 3.7 et DBAL 4.5 sont facultatifs : seule une application qui stocke un `MarkedUpText` comme objet
intégrable en a besoin, et elle les exige elle-même.

## Installer

```sh
composer require phil/markup
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Dans une application Symfony, `phil/markup-bundle`
enregistre `DefaultCompiler`, `SanitizerPurifier` et `Markup` comme services, ainsi que les filtres Twig.
