<!-- phil-dev source-sha256=10e0e848ebe46542ac89ee3e2e45a60e20e277435b956df9389ae622e14d3d60 -->
# Produits

Un produit est un élément comme un autre : ses mots-clés sont ses catégories, sa marque, sa collection. Une boutique
ajoute trois sortes de voisins.

## Relations typées

Le marchand les fixe, par `Contract\TypedRelations` :

| Type | Affichée sur | Exemple |
|---|---|---|
| `RelationType::Accessory` | la source seulement | des pochettes pour un jeu de cartes |
| `RelationType::Complement` | les deux produits | un livre de règles et l'écran du meneur de jeu |
| `RelationType::Upgrade` | la source seulement | l'édition de luxe d'un jeu |

`TypedRelations::from($id)` retourne les relations qui partent du produit, `to($id)` celles qui y arrivent ; le moteur
lit tous les types de la première et seulement les compléments de la seconde. Elles viennent après les épingles et
avant les voisins pointés, avec la raison de leur type : un gabarit affiche `byReason(Reason::Accessory)` comme son
propre bloc.

## Achetés ensemble et vus ensuite

`PairRecorder` transforme ce qui se passe en comptes anonymes par paire, au moment où cela se passe :

```php
use Phil\RelatedContent\Pairs\PairRecorder;

$recorder = new PairRecorder($pairCounts);

// When an order is paid: each pair of distinct products counts once, whatever the quantities.
$recorder->order(['product:game', 'product:sleeves', 'product:dice']);

// When a product page follows another in the same visit.
$recorder->viewedNext('product:game', 'product:expansion');
```

Une paire achetée ensemble est la même paire dans les deux sens ; une vue compte du premier produit au second
seulement. Une commande associe au plus `maxItemsPerOrder` produits distincts, 50 par défaut et 200 au plus : une
commande de n produits fait n × (n − 1) / 2 paires.

Le moteur lit les paires les plus fortes de l'élément, ignore celles qui sont sous `RelevanceRules::$minimumPairCount`,
et ramène les autres à l'échelle de la plus forte.

### Ce qui n'est jamais conservé

Un compte de paire contient deux identifiants de produit et un nombre. La bibliothèque ne conserve, et ne demande à un
`Contract\PairCounts` de conserver, rien d'autre : aucun client, aucun numéro de commande, aucune session, aucune date
d'achat, aucune suite de vues. La paire du produit précédent et du produit courant d'une visite est tenue par
l'application pour la durée d'une requête, puis comptée.

`minimumPairCount`, 3 par défaut, garde une paire hors de toutes les listes tant que plusieurs commandes ou visites ne
l'ont pas produite : une paire vue une seule fois dirait à un lecteur ce qu'une autre personne a acheté.

Quand un produit est retiré, `RelatedContent::retired()` appelle `PairCounts::forget()` : ses paires quittent les
comptes.

`Pairs\InMemoryPairCounts` tient les comptes en mémoire, pour les tests et pour un processus qui les reconstruit au
démarrage. Une application les conserve dans une table de trois colonnes et un compte — les deux identifiants, le
signal — par son propre `PairCounts`, en ajoutant au compte en une seule instruction pour que deux commandes
simultanées n'en perdent pas une.
