<!-- phil-dev source-sha256=ae0a614d1c6101cbee3d36bc2781494e70e8140e7c621686180dba6e313a85a4 -->
# Contrôles de qualité

Les contrôles de l'espace de noms `Check` lisent un segment traduit et signalent ce qui semble anormal dans sa cible par
rapport à sa source : un point perdu, une balise manquante, une forme plurielle dont la langue cible a besoin, une cible
copiée de la source, une étiquette trop large pour son bouton. Ils ne changent jamais un segment ; un traducteur lit les
problèmes et décide.

```php
use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\Checker;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\LanguageTag;

$checker = Checker::defaults();

$issues = $checker->check(new Checked(
    source: '<b>Ready?</b>',
    target: 'Prêt',
    sourceLanguage: LanguageTag::of('en'),
    targetLanguage: LanguageTag::of('fr-CA'),
    flags: Flags::parse('ignore-same'),
));

foreach ($issues as $issue) {
    echo $issue->check, ': ', $issue->message, "\n";
}
// punctuation: The source ends with “?”, the target with no punctuation.
// tags: The target lacks the tag <b>.
// tags: The target lacks the tag </b>.
```

`Checked::segment($segment, $sourceLanguage, $targetLanguage, $flags)` construit la même entrée à partir d'un `Segment`,
et donne `null` quand le segment n'a pas de cible.

## Les contrôles

| Nom | Classe | Ce qu'il signale |
|---|---|---|
| `punctuation` | `Punctuation` | La source et la cible se terminent par des marques différentes (point, `!`, `?`, `:`, `;`, points de suspension, virgule), ou l'une se termine par une marque et l'autre par aucune. Les marques pleine chasse comptent comme leurs équivalents latins, `...` comme `…` ; les espaces avant les marques françaises et les guillemets et crochets fermants après la marque sont ignorés |
| `spaces` | `Spaces` | La cible commence ou se termine par un espace blanc là où la source ne le fait pas, ou l'inverse ; la cible contient deux espaces de suite et la source aucun |
| `tags` | `Tags` | Une balise de la source absente de la cible, ou écrite moins souvent ; une balise de la cible que la source ne contient pas. Les balises sont comparées telles qu'écrites, attributs compris |
| `plurals` | `Plurals` | La source contient un pluriel ICU, `{count, plural, one {…} other {…}}`, et la cible n'a pas de pluriel du même nom, lui manque une catégorie dont sa langue a besoin pour les nombres entiers, ou a des accolades non équilibrées |
| `same` | `SameAsSource` | La cible en texte brut est la source en texte brut, mot pour mot et casse comprise, et la source contient une lettre |
| `max-size` | `MaxSize` | La ligne la plus large de la cible est dessinée plus large que sa limite, en pixels |
| `glyphs` | `Glyphs` | Un caractère de la cible que la police du gabarit ne peut pas dessiner |

`Checker::defaults()` exécute les cinq premiers. Avec un `TextWidth`, et une limite ou des segments qui en portent une,
il ajoute `max-size` ; avec un `Phil\Fonts\Font`, il ajoute `glyphs`. `new Checker([...])` exécute n'importe quelle
liste de contrôles, y compris ceux de l'application, dans l'ordre donné.

### Catégories de pluriel

`Plurals::categories($language)` donne les catégories dont une langue a besoin pour les nombres entiers, d'après les
règles cardinales du CLDR, par langue primaire :

| Catégories | Langues |
|---|---|
| `one`, `other` | af, bg, ca, da, de, el, en, es, et, eu, fi, fr, gl, hu, it, nb, nl, nn, no, pt, sv, tr |
| `one`, `few`, `other` | bs, cs, hr, lt, sk, sr |
| `one`, `few`, `many`, `other` | be, pl, ru, uk |
| `one`, `two`, `other` | he |
| `one`, `two`, `few`, `many`, `other` | ga |
| `zero`, `one`, `two`, `few`, `many`, `other` | ar, cy |
| `other` | toute autre langue |

Les cas explicites comme `=0` sont bienvenus et jamais exigés. Le texte entre apostrophes est littéral, comme ICU le
lit, et les arguments s'imbriquent sur seize niveaux au plus.

## Indicateurs

Les `Flags` d'un segment règlent ses contrôles. `ignore-<name>` écarte un contrôle sur ce segment (`ignore-same` pour
un nom gardé tel quel, `ignore-tags` pour une cible qui abandonne une balise exprès), et `max-size:<pixels>` fixe la
plus grande largeur à laquelle la cible peut être dessinée.

```php
$flags = Flags::parse('ignore-same, max-size:240');

$flags->dismisses('same');        // true
$flags->value('max-size');        // '240'
$flags->with('ignore-tags');      // a copy with one more flag
$flags->without('max-size');      // a copy without it
$flags->toString();               // 'ignore-same, max-size:240'
```

Un nom est une lettre minuscule suivie d'au plus 31 autres lettres minuscules, chiffres et traits d'union ; une valeur
compte de 1 à 32 lettres, chiffres, points, traits de soulignement et traits d'union ; un segment contient au plus 32
indicateurs, et le même nom donné deux fois garde la dernière valeur. Tout le reste lance `InvalidConfiguration`. La
bibliothèque ne conserve pas les indicateurs : l'application les garde avec ses segments, comme le fait
`phil/translation-bundle`.

## Taille rendue en pixels

`MaxSize` mesure chaque ligne de la cible (coupée à `<br>` et aux sauts de ligne, balises omises, références de
caractères décodées) avec un `TextWidth`, et signale la plus large quand elle dépasse la limite : l'indicateur
`max-size` du segment, sinon la limite donnée à `MaxSize`. Sans l'une ni l'autre, le contrôle passe.

`AdvanceWidths` est le `TextWidth` d'une police : la chasse de chaque point de code en unités de police, les unités par
em de la police, la taille à laquelle le gabarit la dessine en pixels, et une chasse de repli pour les caractères qui
manquent à la table.

```php
use Phil\TranslationMemory\Check\AdvanceWidths;
use Phil\TranslationMemory\Check\MaxSize;

$width = new AdvanceWidths(unitsPerEm: 1000, size: 16.0, advances: $advances, fallback: 600);
$checker = Checker::defaults($width, maximum: 240.0, font: $font);
```

La largeur est la somme des chasses : le crénage et les ligatures sont omis, ce qu'une police proportionnelle dessine à
quelques pour cent près. Les unités par em vont de 16 à 16384, la taille est supérieure à 0 et d'au plus 1000 pixels, et
aucune chasse n'est inférieure à 0.

`Glyphs` lit la table des caractères d'une face de `phil/fonts` (`FontFile::fromFile($path)->face()`) et signale les
caractères de la cible qu'elle ne couvre pas, espaces blancs exclus, dix au plus par problème. Un caractère qui manque à
la police est dessiné par une police de repli, ou comme une boîte vide, et sa largeur mesurée est fausse : les deux
contrôles vont ensemble.

## Écrire un contrôle

Un contrôle implémente `Phil\TranslationMemory\Check\Contract\Check` : `name()`, en lettres minuscules et traits
d'union, que l'indicateur `ignore-<name>` écarte, et `check(Checked $checked): list<CheckIssue>`. Un contrôle ne garde
aucun état entre deux appels. Voir [Points d'extension](extension-points.md).
