<!-- phil-dev source-sha256=f104a6a8358af9e79f2c7e60a47f199f5a10f501c9d34f0dcc48805b3c9d3ded -->
<!-- phil-dev source-sha256=0 -->
# Conventions

Une boutique qui vend des chandails imprimés dans des conventions prépare un stock pour chacune, taille par taille, et
imprime à la demande les chandails qu'elle vend en ligne. `ConventionStock` garde les quantités préparées pour chaque
convention et décide, pour une commande passée en ligne, si ses chandails viennent de ce stock ou vont à l'impression.

```php
$conventions->schedule('fall-con', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
$conventions->enter('fall-con', 'SHIRT-M', 20);
$conventions->enter('fall-con', 'SHIRT-L', 10);
$conventions->planned('fall-con');     // ['SHIRT-L' => 10, 'SHIRT-M' => 20]
$conventions->shortfall('fall-con');   // ['SHIRT-L' => 10, 'SHIRT-M' => 14]: 6 medium are on the shelves
```

| Méthode de `ConventionStock` | Effet |
|---|---|
| `schedule(code, name, startsOn, endsOn)` | Ajoute une convention, ou déplace les jours d'une convention déjà connue |
| `enter(convention, sku, quantity)` | Fixe combien d'unités d'un *SKU* — une taille de chandail — sont préparées pour elle ; zéro ou plus |
| `planned(convention)` | Les quantités saisies, par *SKU* |
| `shortfall(convention)` | Ce qui reste à imprimer : les quantités saisies au-delà de ce que les emplacements ouverts ont de disponible maintenant |
| `near(at)` | Les conventions dont un jour tombe dans la fenêtre à partir de ce moment, la plus proche d'abord |
| `toPrint(order, wanted, at)` | Les articles de convention d'une commande passée en ligne qui vont à l'impression, sans rien enregistrer |
| `print(orderNumber, printed, at)` | Les enregistre et les remet aux services `PrintOnDemand`, une fois par commande et par *SKU* |
| `routed(orderNumber)` | Si certaines unités de la commande sont déjà allées à l'impression |

## Le chemin d'une commande passée en ligne

`Stock::reserve()` interroge d'abord `ConventionStock`. Pour chaque article de convention de la commande — un *SKU* dont
une quantité est saisie pour une convention quelconque :

- **tant qu'une convention est proche** — un de ses jours tombe entre maintenant et `window_days` jours plus tard, 30 par
  défaut, les deux jours compris —, l'article va à l'impression, et le stock reste pour la convention ;
- **quand aucune n'est proche**, l'article est réservé dans le stock si les emplacements ouverts en ont assez de
  disponible, et va à l'impression en entier sinon.

Les autres lignes sont réservées comme d'habitude, et un manque sur elles refuse quand même la commande avant que
quoi que ce soit n'aille à l'impression. Dès que des unités sont allées à l'impression, le chemin de la commande est
décidé : la réserver de nouveau ne change rien.

Une commande compte comme passée en ligne quand elle n'a aucun canal, ou quand son canal est l'un des `online_channels`.
Une commande d'un autre canal — la caisse de la convention elle-même — prend dans le stock comme toute commande.

## L'impression à la demande

`Application\Port\PrintOnDemand` est ce que l'application fait des unités envoyées à l'impression. Un service qui
l'implémente reçoit l'étiquette `phil_commerce_logistics.print_on_demand` par autoconfiguration, et est appelé une fois
par commande et par *SKU*, dans la transaction qui réserve la commande. Il met en file un message qui porte le numéro de
la commande et le *SKU*, et lit le reste — le motif, la taille, le texte de l'acheteur — dans son propre stockage.

```php
final readonly class LocalPrinter implements PrintOnDemand
{
    public function __construct(private MessageBusInterface $bus) {}

    public function print(string $orderNumber, string $sku, int $quantity): void
    {
        $this->bus->dispatch(new PrintShirts($orderNumber, $sku, $quantity));
    }
}
```

Le module ne génère ni *SKU* ni motifs ; il lit telles quelles les *SKU* des lignes de la commande.

## Configuration

```yaml
phil_commerce_logistics:
    conventions:
        window_days: 30           # 0 to 366
        online_channels: [web]
```

## Stockage

| Table | Contient |
|---|---|
| `phil_commerce_logistics_convention` | `code` (clé primaire), `name`, `starts_on`, `ends_on` (dates, indexées) |
| `phil_commerce_logistics_convention_quantity` | `convention` et `sku` (clé primaire, indexée par *SKU*), `quantity` |
| `phil_commerce_logistics_print_job` | `id` (UUID v7), `order_number`, `sku`, `quantity`, `sent_at` ; unique par commande et par *SKU* |
