<!-- phil-dev source-sha256=344c47fb78777a19c51cd36e49ae460bf1edb314f6db3ab0038b10f9496f3c3d -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Promotion`, `PromotionCode`, `BundleGroup` | Chaque action et ses bornes, chaque condition et chaque refus, les lots et leurs groupes, la livraison gratuite et ses modes, les exclusions, les incompatibilités dans les deux sens, les utilisations réclamées jusqu'aux limites et libérées, les libellés et leurs traductions |
| `PromotionRedemption`, `PromotionCustomerUse`, `PromotionRefused` | Leurs bornes, les utilisations d'un client jusqu'à la limite, chaque message |
| `PromotionEvaluator`, `PromotionProcessor`, `PromotionOrder` | Chaque action par un vrai noyau avec ses taxes après les rabais ; les lots remplis à partir des lignes les moins chères, plusieurs lots, un lot après un pourcentage de rabais, les minimums ; l'ordre fixé par `after`, une boucle écartée ; l'exclusivité et l'incompatibilité ; les utilisations par client et les produits possédés ; les codes reconnus quelle que soit leur casse et consultés une seule fois ; les origines des rabais ; les produits seulement |
| `FreeShippingProcessor` | L'annulation des frais de livraison sous le libellé et l'origine de la promotion, par mode et par minimum, la première livraison gratuite seulement, les frais neutres laissés, un second passage qui ne change rien |
| `Promotions` | Les lots de codes ; les utilisations comptées une seule fois par commande à partir de ce qu'elle a obtenu, pour le client aussi ; une utilisation prise entre-temps qui refuse le passage |
| `PromotionTexts` | Les libellés lus le long d'une chaîne de repli à partir des traductions stockées, une traduction écrite dans sa propre locale, les rabais libellés dans la locale de l'acheteur, les libellés sans traduction |
| Mappage | Chaque colonne des cinq tables, telles que les crée la migration d'une application |
| `PhilCommercePromotionsBundle` | Les processeurs et leur ordre, les services et leurs arguments, les traductions configurées ou non, l'adaptateur et le mappage Doctrine, le refus sans le noyau ou le catalogue |

Une suite fonctionnelle, sous `tests/Functional/`, démarre un vrai noyau avec FrameworkBundle, DoctrineBundle sur une
base SQLite en mémoire, le noyau, le catalogue et le module : `PromotionsFlowTest` applique un rabais à une commande
une seule fois par un code, compté dans la transaction qui la passe et une seule fois, donne la dernière utilisation
d'un code à usage unique à une commande sur deux, réclame les utilisations d'un client sous la limite, stocke et relit
un lot et sa traduction, et refuse de réclamer une utilisation hors d'une transaction. Quand le module est installé
seul, là où FrameworkBundle est un prérequis de développement, la suite est sautée.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-promotions-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Dans le monodépôt, Infection maintient le module à 100 % des mutants couverts.

## Bancs d'essai

Le banc d'essai phpbench de `tests/Benchmark/` mesure `PromotionEvaluator::evaluate()` sur des paniers de 5 à 100
lignes contre 3 à 30 promotions, un lot toutes les quatre, chacune ordonnée après la précédente. Depuis la racine du
monodépôt :

```bash
composer bench -- packages/commerce-promotions-bundle/tests/Benchmark
```
