<!-- phil-dev source-sha256=55f961940a2817e06ffb3d28998290317be55ba4208818c227e234337aa451f6 -->
# Menaces

Ce que du CSS écrit par quelqu'un d'autre peut faire à une page, et ce que l'assainisseur y fait.

| Menace | Comment le CSS s'y prend | Ce que fait l'assainisseur |
|---|---|---|
| Exécuter du code | `expression()`, `behavior`, `-moz-binding`, URL `javascript:` | Toujours refusé, quoi que dise la politique ; les noms sont lus une fois leurs échappements résolus, donc `e\78 pression()` est `expression()` |
| Charger une autre feuille de style | `@import` | Refusé tant que la politique ne permet pas `AtRuleKind::Import` ; ensuite, seulement au début de la feuille, son URL une feuille de style que le réécrivain permet, avec `UrlKind::StyleSheet`, jamais un fragment |
| Charger depuis un autre site, ou pister le lecteur | `url()` dans les arrière-plans, les puces de liste, les polices, les curseurs ; les images d'`image-set()`, avec ou sans guillemets, avec `type()` à côté | Chaque URL passe par le réécrivain, une chaîne d'`image-set()` comme toute `url()` ; `type()` ne prend qu'une chaîne et ne charge rien ; celui par défaut les refuse toutes ; `AllowedFiles` les garde sur les fichiers du projet |
| Lire la page | Sélecteurs d'attribut sur la valeur des champs avec un arrière-plan par lettre ; `@font-face` avec un `unicode-range` par caractère ; `:visited`, `:has()`, `:checked` | Sélecteurs d'attribut limités à `lang` et `dir` ; ces pseudo-classes non permises ; et aucune URL ne quitte les fichiers de l'application |
| Couvrir la page de l'application | `position: fixed` avec un `z-index` élevé, une boîte posée par-dessus des boutons ou des formulaires | `position`, les décalages et `z-index` non permis par défaut ; une portée garde les règles dans la partie de la page qui appartient à l'utilisateur |
| Sortir de la portée par l'imbrication | `:not(&)` ou `:is(:not(&))` dans une règle imbriquée, qui correspond à chaque élément que son parent ne désigne pas ; `&` au premier niveau, où il désigne la racine | `&` est refusé dans les arguments de `:not()`, `:is()` et `:where()` et au premier niveau ; ailleurs, il représente le parent, déjà soumis à la portée, et les sélecteurs qui le contiennent n'atteignent que cet élément, ses descendants et ses frères |
| Sortir de l'élément `<style>` | `</style><script>` dans une chaîne ou un nom | La sortie ne contient jamais `</` suivi d'une lettre : `<` est échappé dans les noms et les chaînes, et tenu à l'écart de `/` |
| Épuiser le serveur | Des mégaoctets de CSS, des milliers de parenthèses imbriquées, des règles imbriquées dans des règles, des centaines de milliers de règles refusées | Une limite de longueur et une limite de profondeur, chacune refusant la source en entier ; une limite de niveaux d'imbrication, au-delà de laquelle les règles imbriquées sont retirées ; l'analyseur est linéaire, y compris pour un bloc de nombreuses règles imbriquées, et sa récursion bornée ; les 100 premiers retraits listés et les autres comptés, de sorte que `SafeCss` donne au plus 101 violations |
| Passer en contrebande par une différence d'analyse | Commentaires dans les noms, échappements, chaînes et URL non fermées, accolades égarées | L'analyseur lexical et l'analyseur suivent CSS Syntax Level 3 : l'assainisseur lit ce que lit le navigateur, et la sortie est réécrite à partir des jetons gardés |

## Ce qui reste à l'application

- **Le réécrivain.** Il décide quels fichiers une feuille de style peut charger. Un réécrivain qui renvoie toute URL
  qu'on lui donne laisse la feuille de style charger depuis n'importe où, et donc renseigner sur le lecteur.
- **Les feuilles de style que charge `@import`.** Ce passage ne les assainit pas : permettez `AtRuleKind::Import`
  seulement avec un réécrivain qui renvoie, pour `UrlKind::StyleSheet`, des feuilles de style que l'application a
  écrites ou assainies elle-même.
- **La portée.** Sans portée, les règles d'un utilisateur s'appliquent à toute la page où elles sont écrites. Donnez
  une portée partout où des styles d'utilisateurs partagent une page avec l'interface propre à l'application, et
  écrivez-les dans un élément qui la porte.
- **L'échappement pour les attributs.** La sortie de `sanitizeDeclarations()` est du CSS, pas du HTML : échappez-la
  comme toute valeur d'attribut.
- **La politique.** Chaque propriété ou pseudo-classe ajoutée élargit ce que le CSS peut faire. `position`, `z-index`
  et les sélecteurs d'attribut, en particulier, ne devraient être permis que là où la page appartient à l'utilisateur
  seul, comme un document envoyé à un moteur PDF.
