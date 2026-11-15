<!-- phil-dev source-sha256=dbeb5a814b69517e3a5a780c5bb82f4c7978180b5138a3c2c7e1c2e1b07a9386 -->
# Options et lignes configurées

Un article vendable peut avoir des options : un texte que tape l'acheteur, un choix parmi des images, une encre
imprimée comme une couche à part, une valeur tirée une fois la commande payée, un montant que choisit l'acheteur. Un
catalogue les déclare par article vendable ; une ligne de commande porte les valeurs choisies, sa **configuration**,
vérifiée par les options de l'article ; ce que les options ajoutent au prix est un ajustement de la ligne. Le même
article avec d'autres valeurs forme une autre ligne.

| Type | Rôle |
|---|---|
| `Domain\ValueObject\OptionSet` | Les options d'un article vendable : vérifie une configuration, donne ses suppléments |
| `Domain\ValueObject\OptionDefinition` | Une option : code, libellé, nature, exigée ou non, supplément, et ce dont sa nature a besoin |
| `Domain\Option\OptionKind` | `Text`, `Choice`, `Ink`, `Draw`, `Amount` |
| `Domain\ValueObject\AmountRange` | Les montants qu'un acheteur peut choisir : un minimum de zéro ou plus, un maximum, une devise |
| `Domain\ValueObject\TextConstraints` | La longueur, les caractères, la police, la zone, les sources et les entrées d'un texte |
| `Domain\Option\TextSource` | `Free` (tapé), `List` (choisi parmi les entrées), `Drawn` (tiré parmi elles après le paiement) |
| `Domain\ValueObject\PrintZone` | Où un texte est imprimé : un nom et un rectangle en millimètres |
| `Domain\ValueObject\OptionChoice` | Un choix d'une option à choix ou d'encre : code, libellé, image, supplément |
| `Domain\ValueObject\LineConfiguration` | Les valeurs d'une ligne, par code d'option ; son empreinte ; ses tirages |
| `Domain\ValueObject\OptionValue` | Une valeur : choisie, ou à tirer, puis tirée ; décrite par son option |
| `Domain\Exception\InvalidConfiguration` | Une configuration que les options refusent ; `$option` nomme l'option |
| `Application\Port\SellableOptions` | Étiqueté `phil_commerce.sellable_options` : les options que déclare un catalogue |
| `Application\LineOptions` | Les options d'une référence, tirées du premier `SellableOptions` de son type |
| `Application\OptionSupplementProcessor` | Facture ce qu'ajoutent les options, comme des ajustements `Option`, avant les taxes |
| `Application\Port\OptionDrawer` | Étiqueté `phil_commerce.option_drawer` : tire une valeur pour une unité |
| `Application\OptionDraws` | Fait les tirages d'une commande payée |
| `Domain\Exception\DrawRefused` | Une commande pas encore payée, ou un tireur que personne ne prend en charge |

## Déclarer les options

Un catalogue implémente `SellableOptions` pour les types qu'il vend et retourne un `OptionSet`, ses suppléments dans
la devise de la commande :

```php
namespace App\Commerce;

use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\Money\Currency;
use Phil\Money\Money;

final readonly class ProductOptions implements SellableOptions
{
    public function supports(string $type): bool
    {
        return 'product' === $type;
    }

    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        return OptionSet::of(
            OptionDefinition::choice('frame', 'Frame', [
                OptionChoice::of('oak', 'Oak', '/images/frames/oak.png'),
                OptionChoice::of('walnut', 'Walnut', '/images/frames/walnut.png', Money::of(400, $currency)),
            ], required: true),
            OptionDefinition::text('caption', 'Caption', TextConstraints::of(
                maxLength: 24,
                characters: "\p{L}\p{N} '.,!?-",
                font: 'Serif Bold',
                zone: PrintZone::of('bottom', 20, 180, 160, 15),
            ), supplement: Money::of(500, $currency)),
            OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow in the dark', supplement: Money::of(300, $currency))]),
        );
    }
}
```

| Nature | Déclarée par | Une valeur est |
|---|---|---|
| `Text` | `OptionDefinition::text(code, label, TextConstraints, required, supplement, drawer)` | Un texte dans ses contraintes ; à tirer quand ses sources incluent `Drawn` |
| `Choice` | `OptionDefinition::choice(code, label, list<OptionChoice>, required, supplement)` | Le code de l'un de ses choix |
| `Ink` | `OptionDefinition::ink(code, label, list<OptionChoice>, required, supplement)` | Le code de l'une de ses encres |
| `Draw` | `OptionDefinition::draw(code, label, drawer, supplement)` | Toujours à tirer : jamais choisie, toujours définie |
| `Amount` | `OptionDefinition::amount(code, label, AmountRange, required)` | Des unités mineures de la devise de la plage, en chiffres, dans la plage |

- **Les codes** : le code d'une option compte jusqu'à 32 lettres minuscules, chiffres ou `_`, et commence par une
  lettre ; celui d'un choix compte de 1 à 64 lettres minuscules, chiffres, `_` ou `-`. Chacun est déclaré une fois.
- **Un texte** contient de `minLength` à `maxLength` caractères, au plus 1000, pris dans ses `characters` — une classe
  de caractères PCRE sans ses crochets, reconnue en Unicode — quand la saisie est permise. Une entrée de sa liste est
  acceptée telle qu'écrite quand le choix est permis. Un texte choisi ou tiré dans une liste a ses entrées, et
  seulement alors ; un texte qui peut être tiré nomme son tireur, et seulement alors.
- **La police et la zone** sont ce avec quoi un aperçu dessine le texte et l'endroit où le compositeur du fichier
  d'impression le place ; le bundle vérifie la longueur et les caractères, pas l'ajustement.
- **Les suppléments** ne sont jamais négatifs : un rabais est une promotion. Ce qu'ajoute une valeur est le
  supplément de son option plus celui de son choix, ou, pour un montant, le montant choisi.

## Payer ce qu'on veut

Une option de montant laisse l'acheteur fixer le prix sur la ligne : l'article vendable est au prix de zéro — ou d'une
part fixe —, et le montant choisi s'y ajoute comme supplément de l'option, facturé et taxé avec la ligne. Le catalogue
décide toujours du prix : il fixe la plage, et l'acheteur choisit seulement dedans.

```php
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;

OptionSet::of(OptionDefinition::amount('price', 'Your price', AmountRange::of(Money::of(500, $currency), Money::of(5_000, $currency))));

$modifier->add($order, SellableReference::of('ticket', 'concert'), 2, $context, LineConfiguration::of([
    'price' => OptionValue::chosen('1200'), // 12.00, in minor units
]));
```

- **La valeur est en unités mineures écrites en chiffres** : sans signe, sans point décimal, sans zéro initial, dix-huit
  chiffres au plus. Une valeur hors de la plage, ou une qui n'écrit aucun montant, lève `InvalidConfiguration` en nommant
  l'option.
- **Le maximum est obligatoire** : un montant sans maximum laisserait un acheteur atteindre le plus grand entier, et une
  ligne dont le prix le dépasse.
- **Décrit** avec le montant et sa devise, `12.00 CAD`, ce que montre un reçu.
- **Un autre montant est une autre ligne**, son empreinte différant ; le même montant de nouveau s'ajoute à la ligne.
- **Obligatoire par défaut** ; un montant facultatif laissé vide n'ajoute rien — un pourboire que l'acheteur saute.

## Configurer une ligne

Les valeurs de l'acheteur vont à `OrderModifier::add()` sous forme de `LineConfiguration` ; le serveur les vérifie,
et les prix viennent des résolveurs comme pour toute ligne :

```php
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;

$line = $modifier->add($order, SellableReference::of('product', 'poster-12'), 1, $context, LineConfiguration::of([
    'frame' => OptionValue::chosen('walnut'),
    'caption' => OptionValue::chosen('To Bob, from all of us'),
]));
```

- **Vérifiée par les options de l'article vendable**, `OptionSet::check()` : une option que l'article n'a pas, une
  option exigée laissée vide ou une valeur que son option refuse lève `InvalidConfiguration`, avant que quoi que ce
  soit change. Son `$option` nomme l'option, pour qu'un formulaire affiche l'erreur à côté de son champ.
- **Décrite** : chaque valeur est gardée avec le libellé de son option et ce qu'un reçu en montre — le libellé du
  choix, le texte —, et une valeur à tirer avec son tireur. Un reçu montre les options telles qu'elles ont été
  vendues, quoi que dise le catalogue par la suite.
- **Les options à tirer sont toujours définies**, que l'acheteur donne `OptionValue::toDraw()` ou rien ; un texte
  tiré parmi ses entrées se demande avec `OptionValue::toDraw()`. Les options facultatives laissées vides sont
  abandonnées.
- **Deux lignes du même article vendable avec des valeurs différentes sont deux lignes.** La référence d'une ligne
  porte l'empreinte de sa configuration — seize chiffres hexadécimaux tirés des valeurs choisies —, et sa `value()` se
  lit `product.<fingerprint>:poster-12`. Ajouter de nouveau les mêmes valeurs ajoute à cette ligne. Les ajustements
  d'une ligne configurée nomment cette référence, si bien que les promotions, les taxes et les reçus distinguent les
  deux lignes.
- **Un résolveur, un prix, une sélection est interrogé avec la référence `unconfigured()`** : la configuration
  appartient à la ligne, pas à ce qui est vendu. `SellableReference::fromValue()` relit une référence à partir de sa
  valeur.

## Ce qu'ajoutent les options

`OptionSupplementProcessor` s'exécute avec les autres processeurs, avant `TaxOrderProcessor`. Pour chaque ligne
configurée, il relit les options par `LineOptions`, comme les prix sont relus, et écrit un ajustement `Option` par
option qui ajoute quelque chose : libellé comme l'option, son montant égal au supplément multiplié par la quantité de
la ligne, son origine `option:<code>`. `TaxOrderProcessor` taxe une ligne sur son sous-total avec ces ajustements,
après ses promotions.

## Les tirages après le paiement

Une valeur à tirer est inconnue de tous, la boutique comprise, jusqu'à ce que la commande soit payée. Une fois les
paiements de la commande `Paid`, l'application appelle `OptionDraws::draw()` et stocke la commande :

```php
$drawn = $optionDraws->draw($order, $payments->summary($order));   // how many options were drawn
```

- **Un tirage par unité** : chaque valeur laissée à tirer, sur chaque ligne, est tirée par le premier `OptionDrawer`
  qui prend en charge le tireur que nomme son option, une fois pour chaque unité, numérotée à partir de 1. Un tirage
  est le texte que montre un reçu et que lit le compositeur du fichier d'impression, de 1 à 1000 caractères.
- **Une seule fois** : une valeur tirée n'est jamais tirée de nouveau ; lancer le tirage deux fois ne tire rien de
  plus.
- **Refusé** avec `DrawRefused` pour une commande non payée, ou un tireur qu'aucun service ne prend en charge.
- **Le seul changement qu'acceptent les lignes d'une commande passée** : `Order::recordDraws()` remplit une valeur
  laissée à tirer, jamais une valeur choisie, et seulement sur une commande passée. L'empreinte exclut les tirages, si
  bien que la ligne garde sa référence ; une ligne une fois tirée ne change plus de quantité.
- **La façon dont tire un tireur** — des dés, une entrée d'une liste —, et la possibilité pour un acheteur de vérifier
  le tirage, lui appartiennent.

## Stockage

Une ligne configurée garde sa configuration dans la colonne JSON `lines`, sous `configuration` : sa `version`
(`LineConfiguration::VERSION`, 1) et ses `options` par code, chacune avec sa `value` (`null` pour une valeur à tirer),
son `label`, son `display`, son `drawer` et ses `draws`. Une ligne sans option n'a pas de clé `configuration`. Un
ajustement sur une ligne configurée stocke l'empreinte à côté du type et de l'identifiant de la ligne. Une
configuration d'une autre version, ou qu'aucun code n'aurait pu écrire, est refusée à la lecture comme une erreur de
conversion DBAL.

```json
{"type": "product", "id": "poster-12", "description": "Poster", "quantity": 1, "unit_price": 2500, "currency": "CAD",
 "tax_category": "goods", "configuration": {"version": 1, "options": {
   "caption": {"value": "To Bob, from all of us", "label": "Caption", "display": null, "drawer": null, "draws": []},
   "frame": {"value": "walnut", "label": "Frame", "display": "Walnut", "drawer": null, "draws": []}}}}
```

## Sur les reçus

Un reçu copie les lignes de la commande avec leurs configurations. `ReceiptPdf` imprime chaque option sous sa ligne,
en retrait : son libellé et sa valeur, avec le libellé `option` (`%s: %s`), ou, pour une valeur encore à tirer, le
libellé `to_draw` (`drawn after payment`). Les suppléments sont imprimés avec les autres ajustements
([receipts.md](receipts.md)).
