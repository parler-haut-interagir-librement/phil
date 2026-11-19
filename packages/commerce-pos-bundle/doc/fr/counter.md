<!-- phil-dev source-sha256=29bb217b15ec46cccc95476624ff5a9d37a00deedf57457effb6096d1200bd8c -->
# Au comptoir

Ce que le comptoir ajoute à une vente : les préréglages de vente, l'afficheur client, les tirages saisis à la main, les
billets et le rapport des écarts de caisse.

## Préréglages de vente

Un préréglage est un bouton à la caisse posé sur les canaux du noyau : un congrès, un membre, un acheteur en gros, un
ramassage. Un panier vendu sous l'un d'eux passe par son canal — celui de la caisse quand il n'en nomme aucun — jusqu'à
son groupe de clients, chaque ligne étant de nouveau tarifée par le noyau, et la personne peut devoir être identifiée
avant l'enregistrement de la vente.

```php
$presets->create('member', 'Member', channel: null, customerGroup: 'members', identifiesCustomer: true);
$preset = $presets->apply($order, 'member', 'front', $clock->now());
$order->assignCustomer($customerId);
$checkout->ring($order, $session, 'cashier-7', $clock->now(), $preset);
```

Enregistrer un panier sous un préréglage qui identifie le client, sans client, lève `PosRefused`. La vente garde le
code du préréglage, dans la colonne `preset` de `phil_commerce_pos_sale`. `SalePreset::retire()` retire un préréglage
des boutons ; `active()` liste les autres, par code.

## L'afficheur client

L'écran tourné vers le client — une seconde tablette ouverte par un code QR, alimentée par Mercure — appartient à
l'application ; `CustomerDisplay::show($order)` donne ce qu'il montre :

```php
$cart = $customerDisplay->show($order);
$mercure->publish(new Update('pos/front/display', json_encode($cart->toArray())));
```

`DisplayedCart` contient les lignes — description, quantité, prix unitaire, sous-total —, les articles, les rabais, les
taxes (comprises ou ajoutées), le pourboire, le total, ce qui est payé et ce qui reste, les montants en unités mineures
avec leur devise dans `toArray()`. **Il ne montre rien sur la personne** : ni nom, ni solde de points, ni numéro de
client.

## Tirages saisis au comptoir

Une loot box peut être ouverte devant le client avec de vrais dés. Le paquet qui fournit le tirage fournit un
formulaire pour cela — une implémentation de `Application\Contract\TillDrawForm`, étiquetée
`phil_commerce_pos.till_draw_form` par l'autoconfiguration —, et la caisse l'affiche une fois la commande payée :

```php
foreach ($tillDraws->pending($order) as $draw) {
    // {{ include(draw.template, {draw: draw, unit: 1}) }} for each of the draw.units
}

$tillDraws->record($order, $draw->reference, $draw->option, [['dice' => '3,4'], ['dice' => '6,6']]);
```

| Méthode de `TillDrawForm` | Contrat |
|---|---|
| `supports(string $drawer): bool` | Si elle saisit les tirages du tireur qu'une option nomme |
| `template(string $drawer): string` | Le gabarit Twig du formulaire pour une unité, produit avec `draw` (`PendingDraw`) et `unit` |
| `read(string $drawer, array $entered, OrderLine $line, string $option, int $unit): string` | Le tirage tel qu'un reçu le montre, à partir de ce que le caissier a saisi ; `InvalidValue` quand ce n'est pas un tirage que le tireur peut donner |

`record()` refuse une commande qui n'est pas payée, une option qui n'a plus rien à tirer, et un nombre de saisies autre
que la quantité de la ligne, puis enregistre les tirages comme ceux du noyau — que le reçu montre ensuite. Une option
qu'aucun formulaire ne saisit est laissée aux `OptionDraws` du noyau, qui la tirent par son tireur.

## Billets

Un billet d'une billetterie est un article vendable du noyau comme un autre : ajouté au panier par `OrderModifier::add()`
du noyau sous son type de référence, enregistré et payé à la caisse comme un produit.

## Le rapport des écarts de caisse

`CashDifferences::between($from, $to, $register)` liste les sessions fermées du premier moment inclus au second exclu —
celles de toutes les caisses, ou d'une seule —, chacune avec l'excédent ou le manque que son comptage a trouvé, et les
totaux des excédents et des manques par devise. Une session dont l'écart dépasse `cash_difference_threshold` unités
mineures est signalée :

```php
$report = $cashDifferences->between($monday, $nextMonday);
$report->flagged();   // the sessions someone should look at
```
