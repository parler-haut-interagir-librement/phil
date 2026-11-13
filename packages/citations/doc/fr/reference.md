<!-- phil-dev source-sha256=67a361297e9a2bbdecaa23886357f8fb56e4dfc6049413a3b7eecb1412233dc0 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Model\Reference` | Un document cité : identifiant, type, noms, dates et variables, comme CSL-JSON les conserve. |
| `Model\ItemType` | Les 45 types de documents de CSL 1.0.2 ; `isStandalone()` pour un document publié seul. |
| `Model\NameVariable` | Les variables de noms de CSL : `author`, `editor`, `translator`… |
| `Model\DateVariable` | Les variables de dates de CSL : `issued`, `accessed`… |
| `Model\Name` | Un nom en ses parties, ou un nom littéral. |
| `Model\PartialDate` | Une année, avec un mois et un jour, ou une saison, quand ils sont connus. |
| `Model\CslDate` | Une variable de date : une date partielle ou un intervalle, approximatif ou non, ou un texte littéral. |
| `CslJson` | Lit et écrit du CSL-JSON. |
| `Import\BibTeXReader` | Lit du BibTeX en références. |
| `Import\RisReader` | Lit du RIS en références. |
| `Import\ImportLimits` | Les bornes d'un import. |
| `ReferenceList` | Les références qu'un texte peut citer, sans doublons. |
| `Cite` | Une référence citée à un endroit, avec son repère et ses mots avant et après. |
| `Citation` | Les citations d'un endroit d'un texte. |
| `Locator` | Ce que désigne un repère : page, chapitre, section… |
| `CitationProcessor` | Produit le rendu des citations et de la bibliographie d'un texte dans un style. |
| `RenderedCitations` | Les citations et la bibliographie rendues. |
| `RichText` | Du texte mis en forme, donné en texte brut ou en HTML échappé. |
| `Terms` | Les mots et la typographie d'une langue. |
| `Contract\CitationStyle` | Un style de citation. |
| `Style\AbstractStyle` | Les utilitaires que les styles de la bibliothèque partagent. |
| `Style\AuthorDate` | Un style auteur-date à la manière d'APA. |
| `Style\Notes` | Un style à notes et bibliographie à la manière de Chicago. |
| `Style\Numeric` | Un style numérique à la manière d'IEEE. |
| `Style\BibliographyOrder` | Alphabétique, ou dans l'ordre de la première citation. |
| `Style\Entry` | Une référence dans une bibliographie : son numéro et sa lettre d'année. |
| `Style\CitedItem` | Une citation d'un ensemble de citations, avec son entrée et l'indication qu'elle est la première. |
| `Exception\CitationsException` | Implémentée par chaque exception de la bibliothèque. |
| `Exception\InvalidReference` | Une référence ou un CSL-JSON qui enfreint le modèle. |
| `Exception\InvalidImport` | Du BibTeX ou du RIS illisible, avec sa ligne. |
| `Exception\UnknownReference` | Une citation d'un identifiant que la liste ne contient pas. |
| `Exception\MissingTerms` | Aucun terme pour une langue. |
