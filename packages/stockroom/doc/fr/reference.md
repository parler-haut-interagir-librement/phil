<!-- phil-dev source-sha256=8bdea31931d4dfd26c2a0f9122d5d4b219111a43557ab9bdbcd53316ad777c23 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Stockroom` | Les contenants, les articles et chaque mouvement, écrit dans le journal en même temps qu'il change les soldes ; le `StockReader` |
| `Deployments` | La liste de chargement de chaque événement : planification, prélèvement, déploiement, vérification au retour |
| `LoadListReader` | Lit une liste de chargement écrite en texte et en réécrit une |
| `Container` | Un lieu de la réserve qui contient des articles et d'autres contenants |
| `Item` | Ce que la réserve conserve, compté ou suivi |
| `Tracking` | La façon de compter un article : une quantité, ou une unité |
| `ItemCondition` | Utilisable, en attente de réparation ou retiré |
| `Place`, `PlaceKind` | Où se trouve le stock : un contenant, un événement, un détenteur |
| `Movement`, `MovementKind` | Une ligne du journal : entrée, sortie, transfert, perte, retour |
| `Holding` | Combien de pièces d'un article un lieu contient |
| `LoadLine`, `LoadList` | Ce qu'un événement sort de la réserve |
| `PickLine` | Où trouver ce qu'une ligne de chargement prend |
| `Deployment` | Ce qu'un déploiement a fait, et ce qui lui a manqué |
| `ReturnCheck` | Ce que la vérification au retour a trouvé : retours, manquants, surplus |
| `Shortfall` | Un article dont il y a moins de pièces que prévu |
| `Identifier`, `Text`, `Quantity` | Les formes et les bornes des identifiants, des noms et des notes, et des quantités |
| `Contract\Containers`, `Contract\Items`, `Contract\Balances`, `Contract\Journal`, `Contract\LoadLists` | Les ports des magasins |
| `Contract\StockReader` | Le port de lecture du stock, pour un autre paquet |
| `Memory\InMemoryContainers`, `Memory\InMemoryItems`, `Memory\InMemoryBalances`, `Memory\InMemoryJournal`, `Memory\InMemoryLoadLists` | Les magasins en mémoire, réinitialisés entre les requêtes |
| `Exception\StockroomException` | Toute exception que lève la bibliothèque |
| `Exception\InvalidStock` | Une valeur qu'elle ne peut pas accepter |
| `Exception\UnknownStock` | Un contenant, un article ou une liste de chargement qui n'existe pas |
| `Exception\InsufficientStock` | Un mouvement qui prend plus qu'il n'y a, ou un article qui ne peut pas bouger |
