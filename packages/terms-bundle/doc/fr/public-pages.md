<!-- phil-dev source-sha256=734abd0968755edc66f5c2cd475182c92a010602a1fc023eca220d8337a3602f -->
# Pages publiques

Les routes de `config/routes/pages.php`, sous le préfixe de l'application :

| Route | Chemin | Page |
|---|---|---|
| `phil_terms_policy` | `/{policy}` | La version en vigueur — ou, avant qu'aucune n'entre en vigueur, la plus récente |
| `phil_terms_version` | `/{policy}/v{number}` | Une version, à une adresse qui ne change jamais |
| `phil_terms_changes` | `/{policy}/v{number}/changes/{from}` | Les changements d'une version plus ancienne à une plus récente |
| `phil_terms_accept` | `/accept` | La page d'acceptation d'un membre connecté |

Chaque page d'une politique énumère ses versions avec le moment de l'entrée en vigueur de chacune, celles qui sont
importantes, et un lien vers les changements depuis la version précédente. Une version pas encore en vigueur indique
quand elle le sera ; une version remplacée l'indique. Une politique non déclarée, une version inconnue, ou des
changements qui ne vont pas d'une version plus ancienne à une plus récente donnent un `404`.

## Changements

Avec `phil/text-diff`, les changements sont ceux du HTML des deux versions, dans la locale de la requête : ce qui a été
ajouté est dans `<ins>`, ce qui a été retiré dans `<del>`, et les paragraphes gardent leur structure. Sans lui, les
changements donnent un `404`.

## Cache et session

La page de la version en vigueur est envoyée avec `Cache-Control: public, max-age=300`, une page versionnée et ses
changements avec `public, max-age=86400`. Ces pages n'ouvrent aucune session et ne portent aucun jeton ; la page
d'acceptation porte le jeton de son formulaire et n'est jamais conservée par un cache.

## Les recettes du kit

Les gabarits dessinent leurs parties avec des recettes du kit, installées dans l'application par le Symfony UX Toolkit
depuis le kit PHIL UX ou depuis le kit Shadcn ([Installation](installation.md#installer-les-recettes-du-kit)) :

| Page | Recettes |
|---|---|
| Une politique | `Badge` : l'état de la version — en vigueur, pas encore, remplacée — et la marque d'une version importante |
| Les changements | `Button`, comme liens vers les deux versions |
| La page d'acceptation | `Alert` quand une case est laissée non cochée ; `Field`, `Checkbox` et `Field:Label` pour chaque version ; `Button` |

Elles sont appelées par les noms que donne `phil_terms_kit()` — `ui:Badge` quand les recettes sont dans le
sous-répertoire `ui` — et ne passent que les propriétés que les deux kits partagent : les titres, les listes et les liens
des versions sont ceux des gabarits. Les tests rendent chaque page avec chaque kit.

## Mise en page et mots

Les pages étendent `pages.layout`, un gabarit qui définit les blocs `title` et `content`. Aucun gabarit n'écrit un mot de
sa main : chaque mot est un message du domaine `phil_terms`. Le titre d'une politique est
le message `policy.<key>` du domaine `phil_terms` : le bundle traduit `terms`, `privacy` et `code_of_conduct` en
anglais et en français, l'application traduit les autres. Chaque gabarit peut être redéfini dans
`templates/bundles/PhilTermsBundle/`.

Les liens légaux de `phil/legal-notices-bundle` font partie de la mise en page de l'application, qui inclut
`@PhilLegalNotices/_links.html.twig` ; `phil:terms:check-legal-links` confirme qu'ils sont présents
([Console](console.md)).
