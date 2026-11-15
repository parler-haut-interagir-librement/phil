<!-- phil-dev source-sha256=e09ed740428803e7584f8abf17a4bfeb1074f960a9b8c7f1e03c352469f1bd88 -->
# Reçus et notes de crédit

Une commande passée reçoit **un seul reçu** : une copie de ce qui a été vendu, tel que c'était au moment de
l'émission. Un reçu n'est jamais modifié : **une note de crédit l'annule**, en entier ou pour certaines de ses lignes.
Les deux sont numérotés dans une série propre, **séquentielle et sans trou**.

| Type | Rôle |
|---|---|
| `Domain\Model\Receipt` | Un reçu ou une note de crédit : numéro, nature, commande, émetteur, lignes, ajustements, taxes, totaux |
| `Domain\Receipt\DocumentKind` | `Receipt`, `CreditNote` |
| `Domain\ValueObject\Issuer` | Qui l'émet : le nom, les lignes de l'adresse, un numéro d'inscription par taxe |
| `Application\Receipts` | Émet le reçu d'une commande et les notes de crédit d'un reçu |
| `Application\Port\DocumentNumbering` | Port : le prochain numéro d'une série |
| `Domain\Repository\ReceiptRepository` | Trouve et stocke les reçus et les notes de crédit |

## Configuration

```yaml
# config/packages/phil_commerce.yaml
phil_commerce:
    receipts:
        issuer:
            name: 'Le Magasin inc.'
            address: ['1 rue Principale', 'Québec QC  G1R 1A1']
            tax_numbers: { GST: '123456789 RT0001', QST: '1234567890 TQ0001' }
        receipt_prefix: 'R-'       # the default
        credit_note_prefix: 'CN-'  # the default
        number_digits: 8           # the default: R-00000001
```

Les reçus sont désactivés tant que `phil_commerce.receipts` n'est pas défini ; un émetteur avec un nom est alors
exigé, et la construction échoue sans lui. L'émetteur est copié sur chaque reçu : un changement d'adresse n'atteint
jamais un reçu déjà émis.

## Émettre

```php
use Phil\CommerceBundle\Application\Receipts;

// $receipts: Receipts, from the container; $order: a placed order
$receipt = $entityManager->wrapInTransaction(fn () => $receipts->issue($order, $clock->now()));

$receipt->number;          // "R-00000001"
$receipt->lines();         // the lines as sold
$receipt->taxes();         // ["GST" => 1.50, "QST" => 2.99]: each tax summed over the lines, by its code
$receipt->total();
```

- **Seule une commande passée reçoit un reçu, et un seul** : un panier ou un second reçu lève `ReceiptRefused`.
- **Émettez dans une transaction.** Le numéro est pris sous un verrou qui dure jusqu'à la fin de la transaction : deux
  reçus émis en même temps s'attendent l'un l'autre, et un reçu annulé par un retour arrière rend son numéro. Hors
  d'une transaction, `DoctrineDocumentNumbering` lève une `LogicException`.
- **Un document refusé ne prend aucun numéro** : les vérifications ont lieu avant que la série avance.
- **Deux émissions pour la même commande en même temps donnent quand même un seul reçu.** Les vérifications ont lieu
  de nouveau une fois le numéro pris, sous le verrou de la série : la seconde émission, qui a attendu la première, lit
  son reçu, lève `ReceiptRefused`, et sa transaction est annulée avec le numéro. Il en va de même pour deux notes de
  crédit des mêmes lignes.

## Créditer

```php
$note = $entityManager->wrapInTransaction(fn () => $receipts->credit($receipt, $clock->now()));          // every line left
$part = $entityManager->wrapInTransaction(fn () => $receipts->credit($receipt, $clock->now(), [$line]));  // some lines, whole
```

- **Une note de crédit copie les lignes qu'elle crédite, chacune avec ses propres ajustements** — ses taxes, ses
  promotions — et compte chaque montant en négatif : `$note->total()` est l'opposé de ce qu'elle crédite.
- **Les ajustements propres à la commande** — livraison, frais — vont avec la note qui crédite la dernière ligne
  restante.
- **Une ligne est créditée une seule fois** : une ligne déjà créditée, une ligne absente du reçu, une note sans ligne,
  ou une note d'une note de crédit lève `ReceiptRefused`.
- **Les lignes sont créditées en entier.** Créditer une partie de la quantité d'une ligne, pour le retour de quelques
  unités, n'est pas encore pris en charge.

## Ce que montre un reçu

| Membre | Contient |
|---|---|
| `number`, `kind`, `orderNumber`, `creditedReceipt`, `issuedAt` | Son identité ; `creditedReceipt` est le reçu qu'annule une note de crédit |
| `currency`, `issuer` | La devise de la commande ; l'émetteur tel qu'il était |
| `lines()`, `adjustments()` | Tels qu'ils étaient sur la commande, configurations comprises, quantités et montants positifs |
| `itemsTotal()`, `adjustmentsTotal(?AdjustmentType)`, `total()` | Négatifs sur une note de crédit ; ajustements neutres exclus |
| `taxes()` | Chaque taxe par son code, taxes neutres comprises, négatives sur une note de crédit |
| `includedTaxes()` | Les taxes que les prix incluaient, par code, négatives sur une note de crédit ; vide quand les taxes ont été ajoutées |

## La facture PDF

`ReceiptPdf::render(Receipt)` retourne un reçu ou une note de crédit en PDF : l'émetteur, son adresse et ses numéros
d'inscription, le numéro et la date du document, la commande, le reçu qu'annule une note de crédit, chaque ligne avec
ses options en dessous, le sous-total, les ajustements autres que les taxes, chaque taxe par son code et le total,
les montants dans la devise du reçu. Un long reçu se poursuit sur de nouvelles pages, l'en-tête du tableau répété.
Une description trop longue pour sa colonne se poursuit sur les lignes en dessous, coupée entre les mots, et ne
déborde jamais sur la quantité et les montants.

Chaque option d'une ligne configurée est imprimée sous la ligne, en retrait : son libellé et ce qu'elle contient,
avec le libellé `option` (`%s: %s`), ou `to_draw` (`drawn after payment`) pour une valeur pas encore tirée
([options.md](options.md#sur-les-reçus)).

Quand les prix incluaient les taxes, les lignes et le total sont les prix payés, et les taxes viennent après le
total : chacune par son code avec le libellé `included` (`%s included`), puis leur somme avec `taxes_included`
(`Taxes included`). Un reçu en français passe `['included' => '%s incluse', 'taxes_included' => 'Taxes incluses']`.

```php
return new Response($receiptPdf->render($receipt), 200, ['Content-Type' => 'application/pdf']);
```

Les libellés sont en anglais ; une application qui imprime dans une autre langue déclare son propre service avec ses
libellés — `new ReceiptPdf(['receipt' => 'Reçu', 'credit_note' => 'Note de crédit', ...])` — et peut imprimer en A4,
`PageSize::a4()`. Le moteur d'écriture derrière est le `Document` natif de `phil/paged-media`, avec ses flux
compressés. Par défaut, le reçu est imprimé en Helvetica et Helvetica Bold, en Windows-1252, sans rien d'incorporé.
Les caractères absents de Windows-1252 que contiennent souvent les libellés et les prix s'impriment comme leur
équivalent le plus proche — le signe moins `−` et le trait d'union insécable comme `-`, l'espace fine insécable du
français comme une espace insécable, les espaces fines comme des espaces —, et tout autre caractère comme `?`. Une
application dont les noms ou les libellés exigent une autre écriture passe une police TrueType et sa version grasse,
que le document incorpore réduites aux glyphes qu'il imprime :

```php
use Phil\PagedMedia\Font\TrueTypeFont;

new ReceiptPdf($labels, null, TrueTypeFont::fromFile($regular), TrueTypeFont::fromFile($bold));
```

Un reçu gardé pendant les années qu'exige la loi est imprimé en PDF/A-2u, qui incorpore ses polices et a donc besoin
de la paire TrueType ; le document porte de toute façon le titre du reçu, son émetteur et sa date :

```php
use Phil\PagedMedia\Pdf\Conformance;

new ReceiptPdf($labels, null, TrueTypeFont::fromFile($regular), TrueTypeFont::fromFile($bold), Conformance::PdfA2u);
```

Les autres mentions que la loi exige d'un reçu relèvent de l'application.

## Stockage

Avec DoctrineBundle, un reçu ou une note de crédit est une rangée de `phil_commerce_receipt`, et chaque série une
rangée de `phil_commerce_document_sequence`.

| Colonne | Type | Contient |
|---|---|---|
| `number` | `VARCHAR(32)`, clé primaire | Le numéro du document |
| `kind` | `VARCHAR(16)` | `receipt` ou `credit_note` |
| `order_number` | `VARCHAR(64)`, indexé | La commande |
| `credited_receipt` | `VARCHAR(32)`, nullable, indexé | Le reçu qu'annule une note de crédit |
| `issued_at` | `DATETIME` | Quand il a été émis |
| `currency` | `CHAR(3)` | La devise de la commande |
| `issuer` | JSON | Le nom, l'adresse, les numéros d'inscription |
| `lines`, `adjustments` | JSON (`phil_commerce_order_lines`, `phil_commerce_adjustments`) | Comme sur la commande |

| Colonne de `phil_commerce_document_sequence` | Type | Contient |
|---|---|---|
| `series` | `VARCHAR(16)`, clé primaire | `receipt` ou `credit_note` |
| `last_number` | `BIGINT` | Le dernier numéro donné |
