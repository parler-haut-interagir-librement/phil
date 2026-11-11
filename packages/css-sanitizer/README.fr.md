<!-- phil-dev source-sha256=ed25d53177bfbe534fa9de296ca2ba8c0c28598f3eb7ab4e34003fb05133e7c4 -->
# phil/css-sanitizer

**Laissez les gens styliser leurs pages sans que leur CSS exécute du code, charge ce qu'il ne doit pas ou sorte de son
cadre.**

phil/css-sanitizer lit le CSS écrit par des personnes — une feuille de style, ou les déclarations d'un attribut
`style` — comme le lit la spécification CSS Syntax Level 3. Il garde seulement ce qu'une politique permet, puis le
réécrit. Les propriétés, les fonctions, les sélecteurs et les règles @ viennent de listes d'autorisation. `@media`,
`@supports`, `@page` avec ses seize boîtes de marge, `@font-face` et les règles imbriquées à la manière de CSS Nesting
sont compris et vérifiés. Chaque URL — d'une `url()`, d'un `image-set()`, d'un `@import` que la politique permet —
passe par un réécrivain qui la fait correspondre à un fichier permis par l'application, ou la refuse. `expression()`,
les URL `javascript:`, `behavior` et `-moz-binding` sont refusés, quoi que dise la politique. Ce qui est retiré est
listé, avec sa raison et sa ligne, pour que l'auteur le lise.

Il sert à toute application PHP qui laisse ses utilisateurs écrire du CSS : thèmes et apparences d'un livre ou d'une
page publiés, styles personnalisés d'un profil, styles d'impression d'un document envoyé à un moteur PDF, éditeur de
styles dans un système de contenu. Le résultat peut être écrit sans danger dans un élément `<style>` ou un attribut
`style` d'une page que d'autres personnes lisent.

C'est une simple bibliothèque PHP dont le seul prérequis est `ext-mbstring`. Si `symfony/validator`
est installé, il donne la contrainte `SafeCss`. L'analyseur lexical et l'analyseur suivent CSS Syntax Level 3 section
par section. Ainsi, l'assainisseur voit les mêmes jetons qu'un navigateur — échappements résolus, commentaires
écartés, blocs appariés — et un nom caché derrière un échappement (`e\78 pression`) est bien le nom qu'il est. Rien
n'est filtré par expression régulière sur le texte.

La conception : `Syntax\Tokenizer` et `Syntax\Parser` transforment le texte en valeurs de composants, en règles et en
déclarations. `CssSanitizer` les vérifie selon une `Policy`, réécrit leurs URL par un `UrlRewriter` et renvoie un
résultat `Sanitized` : le CSS, les règles gardées sous forme de `Sheet\StyleSheet`, et les `Removal`. `Serializer`
réécrit le CSS de façon qu'il se relise avec les mêmes valeurs et ne contienne jamais le début d'une balise de fin.
`Validator\SafeCss` transforme les retraits en violations.

## État

| Partie | État |
|---|---|
| Analyseur lexical de CSS Syntax Level 3 : chaque jeton, échappements, commentaires, chaînes et URL invalides, lignes | Implémenté et testé |
| Analyseur de CSS Syntax Level 3 : valeurs de composants, règles, déclarations, `!important`, erreurs d'analyse gardées, profondeur bornée | Implémenté et testé |
| Sérialiseur : identifiants et chaînes échappés comme le fait CSSOM, commentaires là où des jetons se colleraient, aucune balise de fin | Implémenté et testé |
| Listes d'autorisation de propriétés, fonctions, pseudo-classes, pseudo-éléments, attributs et types de médias | Implémenté et testé |
| Sélecteurs : type, classe, ID, attribut, pseudo-classes avec leurs arguments, pseudo-éléments, combinateurs | Implémenté et testé |
| Règles de style imbriquées (CSS Nesting) : sélecteurs relatifs et `&` sous la même grammaire, niveaux bornés par la politique, `@media` et `@supports` à l'intérieur | Implémenté et testé |
| `@media`, `@supports`, `@page` et ses boîtes de marge, `@font-face` avec sources locales et réécrites | Implémenté et testé |
| URL réécrites par une fonction de rappel ou une table de fichiers permis ; fragments gardés ; schémas qui exécutent du code refusés | Implémenté et testé |
| `image-set()` avec chaque image réécrite ; `@import` au début d'une feuille, réécrit vers une feuille de style permise quand la politique le permet | Implémenté et testé |
| `expression()`, `behavior`, `-moz-binding` et fonctions qui chargent une ressource toujours refusés | Implémenté et testé |
| Sélecteur de portée, `!important` et propriétés personnalisées soumis à la politique, limites de longueur et de profondeur | Implémenté et testé |
| Contrainte de validateur Symfony `SafeCss` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, l'assainisseur comme service Symfony. |
| [Assainir](doc/fr/sanitizing.md) | Feuilles de style et attributs `style`, le résultat, les retraits et leurs raisons. |
| [Politique](doc/fr/policy.md) | Ce que permet la politique par défaut, chaque `with…()`, la portée, l'imbrication et les limites. |
| [URL](doc/fr/urls.md) | Réécrivains, fichiers permis, `image-set()`, `@import`, fragments, schémas toujours refusés. |
| [Sélecteurs](doc/fr/selectors.md) | La grammaire des sélecteurs vérifiée, les sélecteurs imbriqués et `&`, et ce que la politique y décide. |
| [Règles @](doc/fr/at-rules.md) | `@media`, `@supports`, `@page` et ses boîtes de marge, `@font-face`, `@import`, les règles imbriquées dans une règle de style. |
| [Menaces](doc/fr/threats.md) | Ce dont l'assainisseur protège, comment, et ce qui reste à l'application. |
| [Syntaxe](doc/fr/syntax.md) | L'analyseur lexical, l'analyseur et le sérialiseur utilisés seuls. |
| [Validateur](doc/fr/validator.md) | La contrainte `SafeCss`. |
| [Exceptions](doc/fr/exceptions.md) | `CssSanitizerException` et ses deux exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui assainit du CSS. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/css-sanitizer
```

```php
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Url\AllowedFiles;

$policy = new Policy()
    ->withUrlRewriter(new AllowedFiles(['paper.jpg' => '/media/42/paper.jpg']))
    ->withScope('.book-page');

$sanitized = new CssSanitizer($policy)->sanitize(<<<'CSS'
    @import url(https://example.com/x.css);
    h1 { color: #702; background: url(paper.jpg); behavior: url(x.htc) }
    @page :first { margin-top: 4cm; @top-center { content: string(title) } }
    CSS);

echo $sanitized->css;
// .book-page h1 {
//   color: #702;
//   background: url("/media/42/paper.jpg");
// }
//
// @page :first {
//   margin-top: 4cm;
//
//   @top-center {
//     content: string(title);
//   }
// }

foreach ($sanitized->removals as $removal) {
    printf("line %d: %s (%s)\n", $removal->line, $removal->reason->describe(), $removal->subject);
}
// line 1: an @import rule (@import)
// line 2: a property that runs code (behavior)
```

## Prérequis

PHP 8.5 ou plus récent et `ext-mbstring` ; `symfony/validator` pour la contrainte `SafeCss`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
