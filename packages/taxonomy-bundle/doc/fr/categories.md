<!-- phil-dev source-sha256=8ff6081af07a39e504fa19be2ac732143e9f18f5ea1bd15fb6f2b9dc615491b5 -->
# Catégories

Une catégorie a une clé — un *slug*, unique —, un parent ou aucun, une position parmi ses sœurs, des noms et des
descriptions par langue, et un chemin depuis la racine, `/a/b/c/`, qui permet de trouver une branche entière d'un seul
coup. Une catégorie est au plus à la profondeur 10, une racine étant à la profondeur 0 : un arbre compte onze niveaux,
et le chemin de la catégorie la plus profonde tient dans sa colonne de 420 caractères.

| Méthode | Ce qu'elle fait |
|---|---|
| `createCategory($key, $parent, $names, $position)` | Une nouvelle catégorie ; une clé déjà prise est une `InvalidTaxonomy` |
| `translate($category, $locale, $name, $description)` | Son nom dans une langue, créé ou modifié |
| `name($category, $locale)` | `fr_CA`, sinon `fr`, sinon la langue de repli, sinon la clé |
| `names($categories, $locale)` | Les noms de plusieurs catégories, par identifiant, leurs traductions lues en une seule requête : seulement celles de la langue, de sa langue de base et de la langue de repli |
| `move($category, $parent, $position)` | La déplace avec sa branche ; sous elle-même ou sous sa branche, ou une branche dont la catégorie la plus profonde dépasserait la profondeur 10, est une `InvalidTaxonomy` |
| `tree($locale)` | Des `CategoryNode` — catégorie, nom, enfants —, les sœurs triées par position puis par clé ; deux requêtes, quelle que soit la taille de l'arbre |
| `categorize($resource, $categories)`, `categoriesOf($resource)` | Donne à une ressource exactement ces catégories ; ses catégories |
| `categorizedIn($category, $withDescendants, $kind)` | Les ressources d'une catégorie et, sauf indication contraire, de toute sa branche |
| `mergeCategories($from, $into)` | Déplace les enfants et les ressources de la première vers la seconde, puis la retire avec ses noms ; l'arbre est lu une fois et les enfants sont écrits en un seul *flush*, quel que soit leur nombre |

Une catégorie qui a des enfants ne peut pas être supprimée de la base de données — le `parent` de ses enfants l'en
empêche — : fusionnez-la, ou déplacez d'abord ses enfants.
