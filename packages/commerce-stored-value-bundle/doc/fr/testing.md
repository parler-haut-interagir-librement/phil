<!-- phil-dev source-sha256=ee326efebb8718960612c35cf9863ae89a6f9be902b7697f02d5580b9a6b5b33 -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Instrument`, `Movement`, `MovementKind`, `GiftCardCode` | Chaque règle, l'expiration, l'annulation, l'historique d'un solde, la lecture et la génération des codes |
| `GiftCards`, `GiftCardSellables`, `StoreCredits` | La vente à la valeur nominale, l'émission une seule fois par commande, la recharge, l'annulation, le crédit accordé par client et par devise |
| `StoredValuePayments`, `StoredValueGateway` | Le paiement de ce que l'instrument peut couvrir, un montant choisi, des refus qui ne laissent rien derrière eux, les remboursements vers l'instrument |
| Correspondance | Chaque colonne des deux tables |
| `PhilCommerceStoredValueBundle` | La configuration et ses bornes, les services avec DoctrineBundle, le vendable seul sans lui, la correspondance, le refus sans le noyau |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce et le module : `StoredValueFlowTest` paie une commande avec une carte-cadeau et du crédit en magasin, puis
rembourse une partie du paiement par carte. Installé seul, là où FrameworkBundle est un prérequis de développement, la
suite est sautée.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-stored-value-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```
