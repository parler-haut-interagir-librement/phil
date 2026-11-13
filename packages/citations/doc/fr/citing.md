<!-- phil-dev source-sha256=619849feb89956459ff74f7378e4cc9a35dd0c0c9aab925e3689990e57dbd4e5 -->
# Citer

## Citations

Une `Citation` est l'ensemble des références citées ensemble à un endroit d'un texte : une parenthèse, une note, un
crochet. Chacune est un `Cite` : un identifiant, un repère et son genre (`Locator::Page` par défaut, ou `Chapter`,
`Section`, `Paragraph`, `Volume`, `Figure`, `Line`, `Note`), et des mots avant ou après.

```php
use Phil\Citations\Citation;
use Phil\Citations\Cite;
use Phil\Citations\Locator;

$citations = [
    new Citation(new Cite('smith2020', '47'), new Cite('knuth1984', '12-14')),
    new Citation(new Cite('roy2015', '3', Locator::Chapter, prefix: 'see')),
    Citation::of('smith2020'),
];
```

## Le processeur

```php
use Phil\Citations\CitationProcessor;
use Phil\Citations\Style\Notes;
use Phil\Citations\Terms;

$rendered = new CitationProcessor(new Notes(), Terms::french())->render($references, $citations, uncited: false);

$rendered->citations;     // list<RichText>, one per citation, in order
$rendered->bibliography;  // list<RichText>, in the style's order
```

Le processeur résout chaque identifiant par la `ReferenceList`, un doublon vers la référence qu'il double, et ramène à
une seule les citations d'une même référence répétées dans une même citation. Il ordonne la bibliographie par ordre
alphabétique — par créateurs, année, titre, selon le classement de la langue — ou par première citation, comme le style
le demande, numérote les entrées, donne les lettres d'année quand le style les veut, et distingue une première citation
d'une citation suivante. Avec `uncited: true`, la bibliographie liste aussi les références jamais citées. Il ne garde
rien entre deux appels.

## Les styles

| Style | Dans le texte | Dans la bibliographie |
|---|---|---|
| `Style\AuthorDate` | « (Smith et al., 2020, p. 47; Knuth, 1984) », les œuvres d'un même auteur regroupées (« (Lee, 2018a, 2018b) ») | « Smith, J. A., Dupont, J.-P., & World Health Organization. (2020). On Testing Café. *Journal of Tests*, *12*(3), 45–67. `https://doi.org/10.1000/xyz` » |
| `Style\Notes` | Le texte d'une note de bas de page : complet la première fois, « Knuth, *The TeXbook*, 14. » ensuite | « Knuth, Donald E. *The TeXbook*. 2nd ed. Reading, MA: Addison-Wesley, 1984. » |
| `Style\Numeric` | « [1, p. 47], [2] », « [3]–[5] » pour trois citations consécutives ou plus | « [1] J. A. Smith, J.-P. Dupont, and World Health Organization, “On Testing Café,” *Journal of Tests*, vol. 12, no. 3, pp. 45–67, May 2020, doi: 10.1000/xyz. » |

Le style auteur-date nomme un ou deux auteurs dans une citation, trois ou plus comme le premier suivi de « et al. »,
jusqu'à vingt dans une entrée ; les œuvres des mêmes auteurs et de la même année prennent une lettre, les œuvres sans
date se lisent « n.d. ». Le style à notes nomme jusqu'à trois auteurs dans une note et dix dans une entrée, écrit un
repère de page sans préfixe et met entre guillemets le titre d'une page Web ; le numéro de la note et son emplacement
relèvent du texte. Le style numérique nomme jusqu'à six auteurs.

Les styles suivent la forme de ces manuels pour les genres courants de documents — articles, livres, chapitres et
communications, ouvrages dirigés, thèses, rapports, pages Web — et ne sont l'implémentation complète d'aucun d'eux.

## Les termes

`Terms` contient les mots et la typographie d'une langue : `and`, `et al.`, les abréviations de directeur, d'édition,
de page et de volume, les guillemets et l'indication que la ponctuation va à l'intérieur ou non, les deux-points, les
mois, les ordinaux des éditions, les étiquettes des repères, la marque des années avant l'ère commune.
`Terms::english()` et `Terms::french()` sont fournis ; `Terms::forLocale('fr_CA')` en choisit un selon la langue et
lève `MissingTerms` pour une autre, pour laquelle il faut un `Terms` construit avec ses propres mots.

Les termes français écrivent « dir. », « s. d. », « 2e éd. », des guillemets avec des espaces insécables, une espace
insécable avant les deux-points, et « 375 av. J.-C. ».

## Texte et HTML

Un `RichText` est une suite de segments de texte, dont certains en italique. `toText()` donne le texte brut ;
`toHtml()` donne du HTML dont chaque caractère est échappé et dont les italiques sont en `<i>`, qu'on peut écrire tel
quel dans une page sans risque. Aucun lien n'est écrit : un DOI ou une URL est du texte.
