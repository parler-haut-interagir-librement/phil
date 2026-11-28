<!-- phil-dev source-sha256=05124b4491326d8db0e230dfce1651d7f1bfa16e5b2bdf6722cc959b96a2484d -->
<!-- phil-dev source-sha256=0 -->
# Syndication

Deux applications, chacune avec sa propre base de données, chacune avec ce module : un **fournisseur**, qui détient le
stock et expédie, et un nombre quelconque de **revendeurs**, qui vendent les produits du fournisseur comme les leurs.
Une application peut être les deux, le fournisseur de certains partenaires et le revendeur d'autres.

## Partenaires

Un partenaire est une autre application : son code — l'`identity` qu'elle a configurée, à laquelle sont adressés les
messages qui lui sont envoyés —, un nom, son rôle, le point de terminaison https où elle reçoit les messages, et la clé
publique `whpk_` avec laquelle ses messages sont vérifiés.

```php
$partners->register('tea-house', 'The tea house', PartnerRole::Supplier, 'https://tea.example/syndication/shop', 'whpk_…');
$partners->register('corner-shop', 'The corner shop', PartnerRole::Reseller, 'https://corner.example/syndication/shop', 'whpk_…');
$partners->feed('corner-shop', ['TEA-1', 'TEA-2'], 300_000);
```

Un revendeur reçoit une liste d'UGS et garde une part de leur prix de détail, en parties par million : à 300 000, un
thé vendu 10,00 coûte 7,00 au revendeur. Un partenaire peut déplacer son point de terminaison ou changer sa clé
(`reach()`), et être suspendu : on n'envoie plus rien à un partenaire suspendu, et on ne l'écoute plus.

## Le flux

Chez le fournisseur, chaque changement d'un produit — son prix, son stock, son retrait — est publié :

```php
$publisher->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 42, true, $clock->now());
```

Un message `product.updated` est mis en file pour chaque revendeur actif à qui cette UGS est fournie, avec le prix de
détail et le coût de ce revendeur. L'application appelle le publieur partout où ses produits changent — un mouvement de
stock, une liste de prix, une modification de produit. Le fournisseur garde aussi chaque prix qu'il a publié à chaque
revendeur, une fois par changement — un mouvement de stock seul n'ajoute rien —, pour vérifier les commandes que ce
revendeur transmet.

Une publication coûte les mêmes quelques requêtes, quel que soit le nombre de revendeurs et d'UGS qui leur sont
fournies : les revendeurs à qui l'UGS est fournie sont trouvés par l'index `phil_commerce_syndication_fed_sku`
(`PartnerRepository::resellersFed()`), et le dernier prix envoyé à chacun est lu pour tous d'un coup
(`PublishedPriceRepository::latestPrices()`).

Chez le revendeur, le message devient un **produit syndiqué**, gardé par fournisseur et par UGS. Un message plus
ancien que celui qui a été appliqué ne change rien, puisque les messages peuvent arriver dans le désordre.

## Vendre un produit syndiqué

`SyndicatedSellables` répond au noyau pour le type de vendable `syndicated`, dont l'identifiant est `supplier:sku` :

```php
$orderModifier->add($order, SyndicatedSellables::reference('tea-house', 'TEA-1'), 2);
```

La ligne est tarifée au prix de détail du flux, puis par chaque résolveur et chaque processeur du noyau comme toute
autre ligne : promotions, prix de gros, taxes. Un produit retiré ou en rupture de stock chez le fournisseur reste
connu, mais n'est vendu par aucun canal : le noyau refuse de l'ajouter.

## Transmettre une commande

Une fois la commande du revendeur passée et payée, ses lignes syndiquées partent vers leurs fournisseurs :

```php
$forwarder->forward($order, ['name' => 'Ada Lovelace', 'line1' => '12 rue Principale', 'city' => 'Montréal', 'country' => 'CA'], $clock->now());
```

Un message `order.forwarded` par fournisseur porte le numéro de la commande, l'UGS, la quantité et le coût unitaire de
chaque ligne, et l'adresse d'expédition, dans la forme convenue entre les partenaires. Chaque transmission inscrit au
registre ce que le revendeur doit au fournisseur — le coût de chaque unité au dernier flux. **Transmettre deux fois ne
change rien** : une commande déjà inscrite au registre avec un fournisseur n'est pas envoyée de nouveau.

Chez le fournisseur, **le chiffre du revendeur n'est pas pris pour acquis**. Le `unit_cost` de chaque ligne doit être
un coût que ce fournisseur a publié à ce revendeur pour cette UGS et cette devise, en vigueur à un moment des derniers
`cost_grace_days` (7 par défaut) : le prix actuel, ou un prix remplacé dans cette période, que porte encore une
commande passée juste avant le changement. Une ligne qui ne correspond à aucun refuse le message entier — `400`, comme
illisible —, et rien n'est enregistré ni transmis. Chaque ligne est d'abord lue et vérifiée — une UGS qui n'est pas
fournie au revendeur, une quantité ou un coût hors des bornes —, puis les prix publiés de toutes ses UGS sont lus en
une requête, et les coûts comparés. Le fournisseur enregistre ensuite ce que le revendeur doit à son propre coût publié,
et remet un `ForwardedOrder` à chaque service `ForwardedOrderHandler` — celui de l'application, qui prépare
l'expédition :

```php
final readonly class ShipForwardedOrders implements ForwardedOrderHandler
{
    public function handle(ForwardedOrder $order): void
    {
        // $order->reseller, $order->orderNumber, $order->lines, $order->shipTo, $order->owed
    }
}
```

Un revendeur ne peut commander que les UGS qui lui sont fournies. `ship_to` parvient aux *handlers* et n'est pas
enregistré par le module chez le fournisseur ; chez le revendeur, il ne reste dans le message sortant que jusqu'à ce
que le message soit livré ou abandonné.

## Suivre les colis

Une fois que le fournisseur expédie une commande transmise, il le dit au revendeur, un message `shipment.sent` par colis :

```php
$tracking->notify('corner-shop', 'R-2026-0412', 'SH-881', ['OOLONG-100G' => 2], $clock->now(), 'Canada Post', 'CP123456789CA');
```

Le message porte le numéro de la commande, l'identifiant du colis chez le fournisseur, ses UGS et ses unités, le
transporteur et le numéro de suivi — jamais où il est allé. Chez le revendeur, l'`Inbox` enregistre le colis et remet un
`PartnerShipment` à chaque service `PartnerShipmentHandler`, qui marque la commande expédiée ou avertit l'acheteur :

```php
final readonly class TellTheBuyer implements PartnerShipmentHandler
{
    public function shipped(PartnerShipment $shipment): void
    {
        // $shipment->partner, $shipment->orderNumber, $shipment->lines, $shipment->carrier, $shipment->tracking
    }
}
```

- **Seule une commande du registre avec le partenaire est suivie**, et seulement pour les UGS et les unités qu'elle
  contient : un colis d'une autre commande, ou de plus d'unités, est refusé des deux côtés.
- Un colis est connu par son identifiant chez le fournisseur : le notifier de nouveau retourne `false` et n'envoie rien ;
  le recevoir de nouveau ne change rien.
- `ShipmentTracking::shipmentsOf(partner, orderNumber)` énumère les colis d'une commande, de l'un ou l'autre côté.

## Les retours

Un acheteur renvoie des marchandises au revendeur, qui les renvoie au fournisseur de la commande :

```php
// On the reseller.
$returns->request('tea-house', 'R-2026-0412', 'RET-31', ['OOLONG-100G' => 1], $clock->now(), 'Canada Post', 'CP987654321CA');

// On the supplier, once the parcel arrived: every unit announced, or those accepted.
$returns->receive('corner-shop', 'RET-31', ['OOLONG-100G' => 1], $clock->now());
```

1. `request()` refuse plus d'unités d'une UGS que la commande n'en contient moins ce que ses retours antérieurs ont
   annoncé, et met `return.requested` en file. Le fournisseur enregistre le retour, vérifie la même borne contre son
   propre registre, et le remet aux services `PartnerReturnHandler` (`requested()`), qui attendent le colis.
2. `receive()` prend les unités acceptées — jamais plus que ce qui a été annoncé —, **les crédite aux coûts unitaires de
   l'écriture de la commande dans son propre registre**, et met `return.received` en file avec le crédit.
3. Le revendeur calcule le crédit à partir de son propre registre : un crédit qui diffère est refusé — `400` —, et rien
   n'est enregistré. Sinon il enregistre le crédit, marque le retour reçu, et appelle `received()` sur les gestionnaires,
   qui remboursent l'acheteur.

Le module ne rembourse pas l'acheteur et ne touche pas au stock : les gestionnaires le font, avec les remboursements du
noyau et la logistique de l'application.

## Le registre

Chaque commande transmise est une écriture par partenaire : négative chez le revendeur, qui doit au fournisseur ;
positive chez le fournisseur, à qui l'on doit. L'écriture garde les lignes de la commande — chaque UGS avec sa quantité
et son coût unitaire —, que le suivi et les retours lisent. Un retour reçu est une écriture à part, sous le numéro du
retour, de signe contraire. Les partenaires règlent de la façon convenue — un virement mensuel, une carte de crédit au
dossier —, et chacun l'enregistre :

```php
$ledger->balance('tea-house', Currency::of('CAD'));          // −1 260.00: owed to the tea house
$ledger->settle('tea-house', Currency::of('CAD'), $clock->now());
```

## Réception et livraison

Le contrôleur remet chaque requête à l'`Inbox`, qui vérifie la signature avec la clé du partenaire, compare
l'horodatage à la tolérance et l'audience à `identity`, ignore un identifiant de message déjà reçu, et applique le
message. Un identifiant de message appartient à l'expéditeur : il est retenu par partenaire, pour qu'un autre
partenaire qui utilise le même identifiant ne soit pas confondu avec lui. Le contrôleur répond `204` pour un message
appliqué ou déjà reçu, `404` pour un partenaire inconnu, `400` pour tout ce qui est refusé — mal signé, périmé,
adressé ailleurs, illisible, un montant hors des bornes, un coût jamais publié — et `413` pour un corps de plus de
1 Mio (`SyndicationWebhookController::MAX_BYTES`), lu jusqu'à un octet au-delà et pas plus loin, avant que la signature
soit vérifiée sur les octets reçus.

Le `Courier` livre la boîte d'envoi : chaque message dû est signé avec la clé de cette application et publié.

| Réponse | Ce que fait le coursier |
|---|---|
| `2xx` | Le message est livré |
| Un `4xx` autre que `404`, `408`, `425` et `429` | Le partenaire l'a refusé, et refuserait de nouveau le même message : il est abandonné sur-le-champ, son erreur gardée |
| Aucune, `404`, `408`, `425`, `429`, `5xx` ou toute autre | La tentative suivante vient après 1, 2, 4, 8… minutes, jusqu'à `max_attempts` ; les autres messages du partenaire attendent l'exécution suivante |

Chaque tentative porte le même identifiant de message : le partenaire l'applique donc une seule fois. Un refus pour un
horodatage périmé signifie que les deux horloges ne s'entendent pas : gardez-les synchronisées. Chaque message est
réclamé pour 30 minutes avant d'être envoyé, en une seule mise à jour atomique : deux exécutions simultanées — l'une
plus lente que la minute du planificateur — n'envoient jamais le même message, et une exécution qui meurt laisse
expirer ses réclamations.

## Conservation

Le travail d'un message se termine quand il est livré ou abandonné. À ce moment, ses renseignements personnels —
`ship_to`, l'adresse d'une commande transmise — sont effacés du message enregistré. Les messages des colis et des retours n'en
portent aucun : des UGS, des quantités, des montants, des transporteurs et des numéros de suivi seulement.
`phil:commerce-syndication:purge` supprime les messages sortants terminés, et les identifiants des messages reçus,
depuis plus de `retention_days` (90 par défaut) : planifiez-la une fois par jour. Un message qui attend encore une
tentative n'est jamais purgé. Une fois un identifiant reçu purgé, un partenaire qui renverrait ce même message le
verrait appliqué de nouveau : gardez `retention_days` bien au-dessus de la plus longue série de nouvelles tentatives
des partenaires, que borne `max_attempts`.
