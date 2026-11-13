<!-- phil-dev source-sha256=20a7a2ed45ce3fedd8508e6d4640e93127c49568594fc41c5c6ce9e7e5fd7ccc -->
# phil/stockroom

**Une réserve d'accessoires : contenants imbriqués, articles, journal de chaque mouvement et listes de chargement par événement.**

phil/stockroom tient ce qu'une troupe, un congrès ou une école possède et prête : des contenants imbriqués les uns dans
les autres — une pièce, une étagère, une caisse —, des articles comptés en quantité de pièces interchangeables ou suivis
un à un, et le solde de chaque article dans chaque lieu. Chaque changement est un mouvement écrit dans un journal : du
stock entre, sort vers un événement ou un détenteur, passe d'un contenant à un autre, se perd ou revient. Un mouvement ne
prend jamais plus que ce qu'un lieu contient ; une unité suivie est dans un seul lieu à la fois ; un article en
réparation ou retiré ne sort pas.

Un événement a une liste de chargement. La bibliothèque dit où prendre chaque ligne, déploie la liste vers l'événement
— tout ou rien, ou autant que le stock le permet —, puis la vérifie au retour : ce qui a été compté retourne chez lui,
ce qui manque est listé et reste dehors jusqu'à son retour ou sa radiation. Les listes de chargement se lisent et
s'écrivent en texte brut, une ligne par article.

C'est une simple bibliothèque PHP : ses magasins sont des ports, avec des adaptateurs en mémoire ; les moments viennent
d'une horloge PSR-20 et sont conservés en UTC. Un autre paquet lit le stock par le port `StockReader` sans le déplacer —
une location qui offre ce qui est en stock, une bibliothèque dont les exemplaires sont des articles de la réserve.

## État

| Partie | État |
|---|---|
| `Container`, `Item`, `Place`, `Movement`, `Holding`, les identifiants et leurs bornes | Implémenté et testé |
| `Stockroom` : contenants imbriqués et déplacés, articles, mouvements d'entrée, de sortie, entre contenants, de perte et de retour | Implémenté et testé |
| `Deployments` : listes de chargement, prélèvement, déploiement, vérification au retour | Implémenté et testé |
| `LoadListReader`, sa cible de fuzzing | Implémenté et testé |
| Les ports et leurs adaptateurs en mémoire, réinitialisés entre les requêtes | Implémenté et testé |
| `StockReader`, le port de lecture du stock | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le câblage des magasins. |
| [Stock et mouvements](doc/fr/stock.md) | Les contenants, les articles, les lieux, les mouvements et le journal. |
| [Listes de chargement](doc/fr/load-lists.md) | La planification, le prélèvement, le déploiement, la vérification au retour et le format texte. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/stockroom
```

```php
use Phil\Stockroom\Deployments;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\Memory\{InMemoryBalances, InMemoryContainers, InMemoryItems, InMemoryJournal, InMemoryLoadLists};
use Phil\Stockroom\Stockroom;
use Phil\Stockroom\Tracking;
use Symfony\Component\Clock\NativeClock;

$balances = new InMemoryBalances();
$stockroom = new Stockroom(new InMemoryContainers(), new InMemoryItems(), $balances, new InMemoryJournal(), new NativeClock());
$deployments = new Deployments($stockroom, new InMemoryLoadLists(), $balances);

$stockroom->addContainer('Warehouse', id: 'warehouse');
$stockroom->addContainer('Crate 3', 'warehouse', 'C-3', 'crate-3');
$swords = $stockroom->addItem('FS', 'Foam sword', Tracking::Quantity, 'crate-3');
$stockroom->receive($swords->id, 'crate-3', 40);

$deployments->plan('tavern-scene', [new LoadLine($swords->id, 12)]);
$deployments->deploy('tavern-scene');                    // 12 swords out to the event
$check = $deployments->checkIn('tavern-scene', [$swords->id => 11]);
$check->missing;                                         // one sword missing, still out at the event
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`, `psr/clock`, `symfony/uid` et `symfony/service-contracts`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
