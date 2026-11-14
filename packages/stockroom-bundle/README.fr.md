<!-- phil-dev source-sha256=3bbb55961483314e51c8bdc6d8bb3dc398fff35dca372b1cc4992c58f848d9a1 -->
# phil/stockroom-bundle

**La réserve de `phil/stockroom` conservée dans la base de données : six tables d'une connexion Doctrine DBAL, des mouvements verrouillés sur leurs articles, et les services configurés.**

phil/stockroom-bundle fait entrer `phil/stockroom` dans une application Symfony. La bibliothèque ne garde aucune base
de données : elle nomme cinq ports de stockage — les contenants, les articles, les soldes de chaque lieu, le journal des
mouvements, les listes de chargement des événements — et fournit des adaptateurs en mémoire. Ce bundle implémente
chaque port sur une connexion Doctrine DBAL, dans six tables sous un même préfixe qui se joignent au schéma que génère
l'ORM, de sorte que `doctrine:migrations:diff` écrit la migration qui les crée ; `phil:stockroom:schema` les montre ou
les crée dans une application sans l'ORM. Le journal est écrit une seule fois : son adaptateur insère et ne change
jamais un mouvement.

`StockroomTransactions` exécute les appels de la réserve dans une seule transaction, les lignes des articles qu'ils
déplacent verrouillées d'abord, de sorte qu'un mouvement et ses soldes sont écrits ensemble et que deux mouvements d'un
même article ne s'écrasent jamais. Quand l'appelant a une transaction ouverte sur la même connexion — les prêts de
`phil/library-bundle` sur la même base de données —, le mouvement la rejoint et est conservé avec les écritures de
l'appelant, ou pas du tout.

Il convient à toute application Symfony qui garde des accessoires, des costumes, des jeux ou du matériel dans une
réserve et les déplace vers des événements, des détenteurs et en retour : une association de jeu de rôle grandeur
nature, un théâtre, une ludothèque, un club qui prête son équipement.

Il repose sur ce que Symfony et Doctrine fournissent déjà : Config et DependencyInjection pour la configuration,
Doctrine DBAL pour les tables et leur schéma, l'événement `postGenerateSchema` de l'ORM pour les migrations, Console
pour la commande.

## État

| Partie | État |
|---|---|
| Adaptateurs DBAL des cinq ports : contenants, articles, soldes, journal écrit une fois, listes de chargement | Implémenté et testé sur SQLite, MySQL 8.4 et PostgreSQL 17 |
| Les tables dans le schéma que génère l'ORM, et `phil:stockroom:schema` | Implémenté et testé |
| `StockroomTransactions` : une transaction, les articles verrouillés dans l'ordre, celle de l'appelant rejointe | Implémenté et testé |
| Configuration : le stockage, la connexion, le préfixe des tables, l'horloge ; les stockages en mémoire pour les tests | Implémenté et testé |
| `Stockroom`, `Deployments`, `LoadListReader` et `StockReader` comme services | Implémenté et testé |
| Pages, formulaires ou administration de la réserve | Pas commencé : l'application écrit les siens sur les services |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la recette, les tables et leur migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_stockroom`, avec un exemple. |
| [Stockage](doc/fr/storage.md) | Les six tables, leurs colonnes et leurs index, les collations, le journal écrit une fois, les transactions et leurs verrous. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports à remplacer, les services à utiliser, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et le test d'une application sur la réserve. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, paramètre et commande du bundle. |

## Démarrage rapide

```sh
composer require phil/stockroom-bundle
bin/console doctrine:migrations:diff   # the six tables
bin/console doctrine:migrations:migrate
```

```php
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;

final readonly class Restock
{
    public function __construct(private Stockroom $stockroom, private StockroomTransactions $transactions)
    {
    }

    public function __invoke(string $item, string $shelf, int $quantity): void
    {
        $this->transactions->run(fn () => $this->stockroom->receive($item, $shelf, $quantity), $item);
    }
}
```

## Prérequis

PHP 8.5 ou une version plus récente, `phil/stockroom`, `doctrine/dbal` 4.5 ou une version plus récente, et les
composants Config et DependencyInjection de Symfony 8.2. DoctrineBundle pour les connexions, `doctrine/orm` pour les
tables dans le schéma généré, `symfony/console` pour `phil:stockroom:schema` — chacun seulement quand il sert.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
