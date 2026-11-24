<!-- phil-dev source-sha256=a0c22f52937a9081f92fea90ae33b14bc501800d069f718c6c283b4b48e94bea -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\CommerceReportsBundle\PhilCommerceReportsBundle` | Le bundle : sa configuration `phil_commerce_reports`, ses rapports étiquetés `phil_reporting.report`, ses adaptateurs. |
| `Application\Contract\SoldLines` | Les lignes vendues pendant une période. |
| `Application\Contract\ReturnedLines` | Les lignes retournées pendant une période. |
| `Application\Contract\StockFigures` | Les chiffres de stock d'une UGS sur une période. |
| `Application\Contract\StockedSkus` | Les UGS en stock, avec leurs noms et leurs catégories. |
| `Application\Report\SalesReport` | Le rapport `commerce_sales` : ventes brutes et nettes selon une dimension et par devise. |
| `Application\Report\SellThroughReport` | Le rapport `commerce_sell_through` : écoulement par UGS ou par catégorie. |
| `Application\Report\DeadStockReport` | Le rapport `commerce_dead_stock` : les UGS en stock sans vente depuis un nombre de jours. |
| `Application\Sales\SalesLedger` | Les lignes vendues et retournées pendant une période, totalisées selon une dimension et par devise, au plus 50 000 totaux. |
| `Application\Sales\SalesTotals` | Les totaux d'une valeur dans une devise : unités vendues et retournées, ventes brutes, remises, retours, `net()`. |
| `Domain\Sales\SalesDimension` | `product`, `category`, `channel`, `location`, `cashier` : la valeur, le libellé et l'en-tête d'une ligne selon chacune. |
| `Domain\Formula\SellThrough` | `ppm(sold, start, received)` : l'écoulement en parties par million, une moitié arrondie vers le haut, nul sans rien à vendre. |
| `Domain\Formula\Dormancy` | `isDormant(figure, now, days)` et `daysWithoutSale(lastSoldAt, now)` ; `DEFAULT_DAYS`, 90, et `MAX_DAYS`, 3650. |
| `Domain\ValueObject\SoldLine` | Une ligne vendue : UGS, produit, catégorie, canal, lieu, caissier, quantité, montant brut, remises, moment. |
| `Domain\ValueObject\ReturnedLine` | Une ligne retournée : les mêmes dimensions, quantité, montant remboursé, moment. |
| `Domain\ValueObject\StockFigure` | Le stock de départ, les unités reçues, les unités disponibles et la dernière vente d'une UGS. |
| `Domain\ValueObject\StockedSku` | Une UGS en stock, son nom et sa catégorie. |
| `Domain\Exception\InvalidFigure` | Une ligne, un chiffre ou une entrée de formule hors de ses bornes. |
| `Infrastructure\Pos\PosSoldLines` | `SoldLines` lu depuis le point de vente et les commandes du noyau de commerce. |
| `Infrastructure\Logistics\LogisticsStockFigures` | `StockFigures` lu depuis le journal de stock du module de logistique. |
| `Infrastructure\Memory\InMemorySoldLines`, `InMemoryReturnedLines`, `InMemoryStockFigures`, `InMemoryStockedSkus` | Les ports sur des éléments donnés une fois. |
| `Infrastructure\DependencyInjection\PortsPass` | Crée un alias de chaque port que l'application a laissé de côté vers l'adaptateur de son module, ou vers son adaptateur en mémoire. |

Les espaces de noms ci-dessus sont relatifs à `Phil\CommerceReportsBundle`. `Domain\ValueObject\Text` et
`Domain\ValueObject\Units` sont internes.
