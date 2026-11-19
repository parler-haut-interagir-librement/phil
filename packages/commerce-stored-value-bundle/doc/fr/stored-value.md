<!-- phil-dev source-sha256=437c01022504784ec96b4d991abdf3c02fdd7255e95c1725d95e3c7363d9b03d -->
# Valeur stockée

Deux instruments gardent une valeur dans une devise : une **carte-cadeau**, connue par son code, au bénéfice de qui la
détient, et le **crédit en magasin** d'un client, connu par ce client. Leurs soldes ne bougent que par des mouvements,
et ne descendent jamais sous zéro.

## Cartes-cadeaux

### En vendre une

Une carte-cadeau est un vendable du noyau, de type `gift_card`, dont l'identifiant est sa valeur :

```php
$orderModifier->add($order, GiftCardSellables::reference(Money::of(5_000, Currency::of('CAD'))), 1);
```

La ligne se lit `Gift card CAD 50.00`, coûte sa valeur et prend la catégorie de taxe configurée. Une valeur au-dessus
de `max_value` n'est pas vendue. Une fois la commande passée et entièrement payée, l'application émet ses cartes :

```php
foreach ($giftCards->issueFor($order, $clock->now()) as $issued) {
    $mailer->send(/* $issued->code->toString(): 7KQ2-M9XA-3TFD-W8HC */);
}
```

Une carte par unité, à la valeur nominale de la ligne, quelle que soit la promotion qui a baissé son prix. **Une
commande qui a encore un montant à payer n'émet aucune carte** : `issueFor()` lève `StoredValueRefused` tant que
`Payments::summary()` du noyau trouve encore quelque chose à payer — un paiement différé ou porté au compte compte une
fois terminé. **Émettre deux fois ne change rien** : les cartes déjà émises de la commande ne sont pas émises de
nouveau. Le code n'est connu qu'à ce moment ; ensuite, le module garde son empreinte et ses quatre derniers caractères.

### Annuler une commande qui a vendu des cartes

```php
$order->cancel($clock->now());
$giftCards->voidFor($order->number, $clock->now(), 'Order cancelled');   // before refunding its payments
```

`voidFor()` annule les cartes encore actives de la commande et les retourne ; chacune perd ce qu'elle contenait, dans un
mouvement `Voided`. Ce qu'une carte a déjà dépensé est parti : ses mouvements disent combien, et l'application le
déduit de ce qu'elle rembourse de la commande.

### En donner une, en recharger une, en annuler une

```php
$sold = $giftCards->issue(Money::of(2_500, Currency::of('CAD')), $clock->now());                  // paid at the counter
$given = $giftCards->give(Money::of(1_000, Currency::of('CAD')), $clock->now(), $clock->now()->modify('+90 days'));
$giftCards->load('7KQ2-M9XA-3TFD-W8HC', Money::of(1_000, Currency::of('CAD')), $clock->now(), 'Top-up');
$giftCards->void('7KQ2-M9XA-3TFD-W8HC', $clock->now(), 'Reported stolen');
```

Une carte annulée perd ce qu'elle contenait et ne sert plus.

### Donnée ou payée

Chaque carte sait d'où vient sa valeur, dans `Instrument::$origin` :

| Origine | Comment elle est émise | Expiration |
|---|---|---|
| `GiftCardOrigin::Given` | `give()`, ou `issue(..., origin: GiftCardOrigin::Given)` : une promotion, un geste, un prix | Permise |
| `GiftCardOrigin::Paid` | `issue()`, et `issueFor()` pour les cartes qu'une commande a vendues | **Refusée**, à moins que la carte soit marquée pour un service précis |

Une carte payée pour un service précis — un repas, un spectacle, un soin — est émise avec `specificService: true`, et
peut alors porter une date d'expiration :

```php
$giftCards->issue(Money::of(9_000, Currency::of('CAD')), $clock->now(), $clock->now()->modify('+1 year'), specificService: true);
```

Une carte payée à qui l'on donne une expiration sans qu'elle soit pour un service précis est refusée par
`InvalidValue`. Un crédit en magasin n'a pas d'origine.

**Ces règles sont le défaut sûr du bundle, pas un avis juridique : chaque boutique vérifie la loi applicable là où elle
vend** avant de donner une expiration à une carte — les lois de protection du consommateur diffèrent selon le pays, la
province et l'État, et certaines refusent une expiration même sur une carte donnée gratuitement ou pour un service
précis. Le bundle ne fixe jamais d'expiration de lui-même.

### En imprimer une

`GiftCardPdf` imprime une carte tout juste émise sur une page de la taille d'une carte de paiement (ID-1, 85,6 sur
53,98 mm), avec le rédacteur PDF natif de `phil/paged-media` : le nom de la boutique, la valeur et sa devise, le code,
et en dessous que la carte est pour un service précis et quand elle expire, si elle expire. Le code n'est connu que
pendant que la carte est tout juste émise : la carte s'imprime alors.

```php
use Phil\CommerceStoredValueBundle\Infrastructure\Pdf\GiftCardPdf;

$printer = new GiftCardPdf('The Shop');
$pdf = $printer->render($giftCards->issue(Money::of(5_000, Currency::of('CAD')), $clock->now()));
```

Les étiquettes sont en anglais par défaut — `gift_card`, `valid_until` (avec `%s` pour la date) et
`specific_service` — ; une application qui imprime dans une autre langue passe les siennes. Helvetica imprime les
langues occidentales ; une boutique dont le nom demande une autre écriture passe une police TrueType et son gras. Un nom
de boutique trop long pour la carte est coupé au dernier mot qui tient.

### Codes

Seize caractères en Base32 de Crockford — sans I, L, O ni U —, en quatre groupes. La lecture pardonne ce qu'une
personne tape : les minuscules, les espaces, les traits d'union, et I, L et O lus comme 1, 1 et 0. Quatre-vingts bits
de hasard mettent la devinette hors de portée **tant que l'application limite la fréquence à laquelle un code peut être
essayé**, sur la page du solde comme au passage à la caisse.

## Crédit en magasin

```php
$storeCredits->grant('customer-42', Money::of(1_500, Currency::of('CAD')), $clock->now(), 'Return of A-0042', 'A-0042');
$storeCredits->balance('customer-42', Currency::of('CAD'));
```

Un solde par client et par devise, ouvert la première fois qu'un crédit est accordé. Un client est nommé par ce que
l'application utilise — un compte, un locataire, un courriel.

## Payer avec la valeur stockée

```php
$payment = $storedValuePayments->payWithGiftCard($order, '7KQ2-M9XA-3TFD-W8HC', $clock->now());
$payment = $storedValuePayments->payWithStoreCredit($order, 'customer-42', $clock->now(), Money::of(1_000, Currency::of('CAD')));
```

Chaque appel est un paiement du noyau, de méthode `gift_card` ou `store_credit` : créé, la valeur dépensée et le
paiement terminé dans la même transaction. Sans montant, il paie autant de ce qui reste que l'instrument en contient ;
le reste est payé par une autre méthode, pour un paiement partagé. Un instrument annulé ou expiré, un instrument vide
et un montant supérieur à ce que contient l'instrument sont tous refusés avant que le paiement existe : un refus ne
laisse donc aucun paiement derrière lui. Chaque paiement est sa propre transaction : le noyau lit ce qui reste à payer
à partir des paiements déjà enregistrés.

## Remboursements

`Payments::refund()` du noyau atteint `StoredValueGateway`, qui rend le montant à l'instrument d'où il a été dépensé et
enregistre aussitôt le remboursement, sous sa clé d'idempotence : un remboursement redemandé sous la même clé ne rend
rien de plus. Rembourser en crédit en magasin ce qui a été payé autrement relève d'un retour : `StoreCredits::grant()`.

Une carte-cadeau annulée ou expirée depuis qu'elle a payé ne reprend aucune valeur. Le remboursement devient alors le
crédit en magasin du client de la commande, un mouvement `Restored` sur ce crédit — ouvert au besoin. Le module connaît
une carte par son code, pas par son détenteur : l'application nomme le client en implémentant
`Application\Port\StoreCreditHolders`, que la passerelle reçoit par injection automatique.

```php
final class OrderCustomers implements StoreCreditHolders
{
    public function holderOf(string $orderNumber): ?string
    {
        return $this->orders->customerOf($orderNumber);   // whatever the application keeps
    }
}
```

Sans ce service, ou pour une commande dont le client est inconnu, un tel remboursement lève `StoredValueRefused`, tout
comme un remboursement vers un crédit en magasin annulé depuis ; l'application rembourse alors par un autre moyen.

## Mouvements

Chaque changement est un mouvement : émis, rechargé, accordé, utilisé, restitué, annulé — son montant, positif quand il
est ajouté, le solde qu'il a laissé, la commande et le paiement qu'il concerne, une note. Les mouvements ne sont jamais
modifiés ni retirés ; `InstrumentRepository::movementsOf()` liste ceux d'un instrument.
