<!-- phil-dev source-sha256=588dc0218e316f0ab481d7cdeffec4b804d20dc58bdb63e261ee79566617d208 -->
# Formulaires

`Document::field()` place un champ de formulaire sur la dernière page : une zone de texte, une case à cocher, un groupe
de boutons radio ou un choix. Un lecteur le remplit dans une visionneuse PDF, puis enregistre ou imprime le document, ce
qui rend remplissable une feuille de personnage, un bon de commande ou une fiche d'inscription. `render()` garde les
champs interactifs ; `render(flattenForm: true)` les dessine dans leurs pages, tels qu'ils sont, pour une copie qui ne
peut plus être modifiée.

```php
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Form\CheckBox;
use Phil\PagedMedia\Pdf\Form\ChoiceField;
use Phil\PagedMedia\Pdf\Form\RadioButton;
use Phil\PagedMedia\Pdf\Form\RadioGroup;
use Phil\PagedMedia\Pdf\Form\TextField;

$pdf = new Document();
$pdf->addPage();
$pdf->text(72, 720, 'Character sheet', 18);
$pdf->field(new TextField('name', 72, 680, 200, 20, border: Color::gray(0), tooltip: 'Character name'));
$pdf->field(new TextField('notes', 72, 560, 300, 100, multiline: true, border: Color::gray(0)));
$pdf->field(new CheckBox('inspiration', 72, 530, border: Color::black()));
$pdf->field(new RadioGroup('size', [
    new RadioButton('small', 72, 500),
    new RadioButton('medium', 92, 500),
    new RadioButton('large', 112, 500),
], selected: 'medium', border: Color::black()));
$pdf->field(new ChoiceField('class', 72, 460, 150, 20, ['war' => 'Warrior', 'mage' => 'Mage'], 'mage'));

file_put_contents('sheet.pdf', $pdf->render());               // fillable
file_put_contents('sheet-filled.pdf', $pdf->render(true));    // flattened
```

## Champs

Chaque champ a un nom, unique dans le document, sans point (un point en ferait l'enfant d'un autre champ) et d'au plus
127 octets. Il est placé à partir du coin inférieur gauche de sa zone, en points depuis le coin inférieur gauche de la
page, comme tout ce que dessine le moteur d'écriture.

| Champ | Ce que c'est | Sa valeur |
|---|---|---|
| `TextField` | Une zone de texte, sur une ligne ou `multiline`, avec un `maxLength` facultatif | Le texte qu'elle contient |
| `CheckBox` | Un carré, coché ou non | Son `exportValue` (`Yes` par défaut) quand elle est cochée, `Off` sinon |
| `RadioGroup` | Des `RadioButton` dont un est choisi, chacun avec sa valeur et sa place | La valeur du bouton choisi, ou `Off` |
| `ChoiceField` | Une liste déroulante (`combo`, par défaut), qui peut être `editable`, ou une liste | La valeur de l'option choisie, ou le texte saisi dans une liste déroulante modifiable |

Un `ChoiceField` prend une liste d'étiquettes, chacune étant sa propre valeur, ou des étiquettes par valeur :
`['war' => 'Warrior']` affiche *Warrior* et donne `war`. Ses `options` sont des paires d'une valeur et d'une étiquette,
et `shown()` est ce que le champ affiche.

Chaque champ peut être `required` ou `readOnly`, et recevoir un `tooltip`, que lisent les technologies d'assistance. Sa
`color` est la couleur de son texte ou de sa marque, noire par défaut ; ses `border` et `background` tracent et
remplissent sa zone, et rien n'est dessiné quand ils ne sont pas donnés : un champ peut donc se placer dans un cadre que
la page imprime déjà.

Une valeur est vérifiée à la construction du champ : du texte UTF-8, sur une ligne sauf si le champ est multiligne, d'au
plus `maxLength` ; une valeur choisie parmi celles du champ, sauf dans une liste déroulante modifiable ; deux boutons ou
deux options d'une même valeur, et une valeur d'exportation `Off`, sont refusés. Chaque refus est une
`InvalidDocument`, comme un second champ du même nom ou un champ placé avant toute page.

## Apparences

Le moteur d'écriture dessine l'apparence de chaque champ quand le champ est placé : le texte dans sa police, renvoyé à
la ligne selon la zone s'il est multiligne, l'option choisie d'une liste déroulante, les options d'une liste avec la
choisie en surbrillance, la coche et le point des boutons, dessinés comme des tracés, sans police de symboles. Une
visionneuse montre le champ tel quel sans rien générer, et le document ne demande aucune génération (`NeedAppearances`
n'est jamais défini). Une visionneuse redessine un champ quand sa valeur change, avec la police et la couleur que le
champ nomme (son `/DA`) et la bordure et le fond qu'il déclare (son `/MK`). Une apparence de moins de 256 octets est
écrite sans compression, ce qui coûte moins que la compresser.

## Polices

Une zone de texte ou un choix saisit en `Helvetica` par défaut, ou dans la `StandardFont` ou la `TrueTypeFont` qu'on lui
donne. Tant que le formulaire est interactif, une police TrueType dans laquelle un champ saisit est intégrée en entier,
avec chaque glyphe que sa table de caractères contient, puisque le lecteur peut taper n'importe lequel ; une fois le
formulaire aplati, elle est réduite aux glyphes imprimés, comme tout autre texte. Une police dont la licence interdit
l'intégration est refusée à sa lecture, comme toujours.

## PDF/A

Un document écrit en `Conformance::PdfA2u` garde ses champs : chaque widget est imprimable, chaque apparence est
fournie, et rien n'en génère une. Ses zones de texte et ses choix doivent saisir dans une `TrueTypeFont`, puisque PDF/A
intègre chaque police : une police standard est refusée quand le champ est placé, comme elle l'est pour `text()`. Les
cases à cocher et les boutons radio n'ont besoin d'aucune police.

## Aplatissement

`render(flattenForm: true)` dessine l'apparence de chaque champ, dans l'état où il est, par-dessus le contenu de sa
page, comme un XObject de formulaire, et n'écrit aucun formulaire : les valeurs font partie de la page. Le même
`Document` s'écrit des deux façons, si bien qu'une application garde une seule feuille et écrit la copie dont elle a
besoin.

## Relire un formulaire

La bibliothèque écrit des formulaires ; elle n'en lit pas un rempli. Gotenberg lit les champs de n'importe quel PDF
(`Gotenberg::form()`) et les remplit ou les aplatit (`SetForm`, `FlattenForm`) : voyez [Lire un PDF](reading.md) et
[Étapes](stages.md).
