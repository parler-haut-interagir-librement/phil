<!-- phil-dev source-sha256=8c2ce884d860e6dc5b4926578dbac7207aad15d3c54afdd7c44fe5a6cf9bd2fc -->
# Traduction

## Le port

```php
interface Translator
{
    public function supports(string $language, Contraction $contraction): bool;

    /** @param list<string> $texts @return list<string> */
    public function translate(array $texts, string $language, Contraction $contraction): array;
}
```

Un texte est une ligne d'imprimé ; son braille revient sous forme de motifs braille Unicode, U+2800 à U+28FF, un
caractère par cellule, l'espace entre deux mots étant la cellule blanche U+2800. `BrailleExporter` envoie tout le
document en un seul appel, pour qu'un traducteur qui démarre un programme le démarre une seule fois, et vérifie la
réponse : autant de textes que d'envoyés, chacun fait de cellules braille seulement.

`Contraction::Uncontracted` va lettre pour lettre — degré 1, « braille intégral » ; `Contraction::Contracted` emploie
les abréviations de la langue — degré 2, « braille abrégé ».

## Le français et l'anglais intégraux

`UncontractedTranslator` écrit en PHP du braille intégral. La langue se lit dans la sous-étiquette primaire de son
étiquette : `fr`, `fr-CA` et `fr_BE` sont du français, `en` et `en-GB` de l'anglais.

| | Français : Code braille français uniformisé | Anglais : Unified English Braille |
|---|---|---|
| Lettres | a à z, et à â ç è é ê ë î ï ô ù û ü œ comme cellules à part | a à z ; une lettre accentuée comme son modificateur, puis sa lettre |
| Une majuscule | points 4-6 avant la lettre | point 6 avant la lettre |
| Un mot en majuscules | points 4-6 deux fois avant le mot | point 6 deux fois avant le mot |
| Un nombre | point 6, puis ses chiffres en notation Antoine | points 3-4-5-6, puis ses chiffres comme les lettres a à j |
| Dans un nombre | une virgule ou un point suivi d'un chiffre | une virgule ou un point suivi d'un chiffre |
| Après un nombre | rien | points 5-6 avant une lettre a à j |
| Ponctuation | les signes courants, guillemets et tirets | les signes courants, parenthèses, guillemets, tirets, et les signes de deux cellules comme `@`, `%` ou `€` |

Une lettre avec un accent pour lequel le code n'a pas de signe s'écrit comme sa lettre de base : `ñ` donne `n` en
français. Tout autre caractère sans braille est omis ; `new UncontractedTranslator(strict: true)` lance plutôt
`UntranslatableCharacter`, pour qu'une application trouve ce que contient son texte.

Le traducteur couvre le texte de la plupart des documents. Un texte qui exige les règles complètes d'un code —
mathématiques, notation informatique, langues étrangères dans le texte, mise en évidence — exige un traducteur à tables
complètes.

## Le braille abrégé et les autres langues

Le braille abrégé suit des centaines de règles par langue, gardées dans les tables d'un programme de traduction en
braille. `phil/braille-export-liblouis` est le traducteur qui exécute liblouis : braille abrégé et intégral de toutes
les langues pour lesquelles liblouis a des tables.

## Une chaîne de traducteurs

`ChainTranslator` demande à chaque traducteur tour à tour s'il prend en charge la langue et la contraction, et donne le
lot au premier qui le fait :

```php
use Phil\BrailleExport\Translation\ChainTranslator;
use Phil\BrailleExport\Translation\UncontractedTranslator;

$translator = new ChainTranslator($liblouis, new UncontractedTranslator());
```

Quand aucun ne le fait, elle lance `UnsupportedTranslation`.

## Le braille Unicode

`Braille` contient les fonctions utilitaires de la forme que prend tout texte une fois traduit :

| Membre | Ce qu'il donne |
|---|---|
| `Braille::cell('1245')` | La cellule de certains points, ici la lettre g ; `''` est la cellule blanche. |
| `Braille::cells('6', '6')` | Les cellules d'un signe de plusieurs cellules. |
| `Braille::isBraille($text)` | Si un texte contient des cellules braille et rien d'autre. |
| `Braille::length($braille)` | Son nombre de cellules. |
| `Braille::BLANK`, `Braille::HYPHEN` | La cellule blanche, et les points 3-6. |
