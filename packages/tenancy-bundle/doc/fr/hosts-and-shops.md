<!-- phil-dev source-sha256=8856e43f6b9f231934b6c6cbc96c9455e1288a49d79e051736fe116a6c952715 -->
# Hôtes et boutiques

Une application peut servir plusieurs locataires, chacun sur son propre domaine : plusieurs boutiques, un sous-site par
événement, un site par club. Le bundle entre, avant le pare-feu, le locataire que sert l'hôte d'une requête, et décrit
les boutiques parmi ces locataires pour qu'un bundle de commerce sache quels catalogues une boutique vend et de quoi
marquer une commande.

## Le locataire de chaque hôte

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    hosts:
        shop.example: general
        rose.example: rose
        tavern.example: tavern
```

Chaque clé est un hôte, écrit seul, et chaque valeur la référence du locataire qu'il sert. `ConfiguredHostTenants` lit
les hôtes comme `phil/url` lit l'hôte d'une adresse :

| Écrit | Lu comme |
|---|---|
| `Shop.Example` | `shop.example` : les hôtes sont comparés en minuscules |
| `bücher.example` | `xn--bcher-kva.example`, la forme qu'envoie un navigateur |
| `shop.localhost` | accepté, pour le développement |
| `shop.example:8443`, `shop.example/fr` | refusés : un hôte n'a ni port, ni chemin, ni requête, ni fragment |
| `192.0.2.1`, `user@shop.example` | refusés : une adresse IP ou des identifiants ne sont pas un hôte |
| `shop.example` et `SHOP.example` | refusés : le même hôte listé deux fois |

Un hôte ou une référence mal formés arrêtent la compilation du conteneur.

`Infrastructure\Http\ResolveHostTenant` s'exécute à chaque requête principale, sur `kernel.request`, avant
`security.firewall` (ou `debug.security.firewall`) et avant `ResolveActiveTenant`. Il lit l'hôte comme le fait
HttpFoundation, après les `framework.trusted_hosts` de l'application, et :

| Hôte | Ce que fait l'écouteur |
|---|---|
| Sert un locataire | Entre le locataire, pour que l'authentification et chaque requête de la requête agissent dedans, puis envoie `Application\Event\HostTenantResolved` |
| Ne sert aucun locataire | Rien : la requête est laissée à `ResolveActiveTenant`, ou s'exécute hors de tout locataire |

Une requête dont l'hôte sert un locataire n'est pas résolue de nouveau par `ResolveActiveTenant` : l'hôte décide, et
`ResolveActiveTenant` vérifie seulement qu'un compte connecté peut atteindre ce locataire, comme le décrit
[integrations.md](integrations.md). Les
sous-requêtes partagent le locataire de la requête principale. Le locataire est quitté entre les requêtes avec le reste
du contexte.

Configurez `framework.trusted_hosts` avec les mêmes hôtes : une requête pour un hôte que l'application ne sert pas est
alors refusée avant qu'aucun écouteur ne la lise. Tant qu'il n'est pas réglé, `ResolveHostTenant` journalise un
avertissement, par le service `logger`, à la première requête que sert l'hôte d'un locataire, une fois par processus :
un *worker* qui sert beaucoup de requêtes avertit une fois, un processus PHP-FPM une fois par requête qu'il traite.

Une application dont les hôtes vivent dans une base de données implémente plutôt `Application\Contract\HostTenants`, et
lui donne un alias : voir [extension-points.md](extension-points.md).

## Les alias d'un hôte

Le site d'un locataire peut répondre sous d'autres noms : `www.` devant son hôte, un ancien domaine, un domaine de la
plateforme qui le servait avant qu'il ait le sien. Chaque alias nomme l'hôte canonique qu'il représente :

```yaml
phil_tenancy:
    hosts:
        rose.example: rose
    host_aliases:
        www.rose.example: rose.example
        rose.platform.example: rose.example
    redirect_host_aliases: true # the default
```

Un alias se lit comme un hôte, et sert le locataire de son hôte canonique. La compilation s'arrête sur un alias qui est
aussi un hôte, un alias listé deux fois, ou un alias d'un hôte absent de `hosts` — un alias d'alias compris.

Avec `redirect_host_aliases`, `Infrastructure\Http\RedirectHostAlias` s'exécute à chaque requête principale avant
`ResolveHostTenant` et le pare-feu, et envoie une requête pour un alias vers son hôte canonique :

| Requête | Réponse |
|---|---|
| `GET https://www.rose.example/cart?item=1` | `301`, `Location: https://rose.example/cart?item=1` |
| `POST https://www.rose.example/cart` | `308`, que le client répète avec son corps |
| `GET https://www.rose.example:8443/` | `301`, `Location: https://rose.example:8443/` : le schéma et le port sont conservés |
| Un hôte qui n'est pas un alias | Rien : la requête continue |

L'adresse vient de la configuration, jamais de la requête : un visiteur n'est envoyé qu'à l'un des hôtes de
l'application. Avec `redirect_host_aliases: false`, un alias sert son locataire là où il est, et l'application indique
elle-même l'adresse canonique aux moteurs de recherche. Listez les alias dans `framework.trusted_hosts` avec les hôtes.

Une application dont les alias vivent dans une base de données implémente `Application\Contract\HostAliases` à côté de
`HostTenants` et enregistre elle-même `RedirectHostAlias`, comme le montre [extension-points.md](extension-points.md).

## Les boutiques

```yaml
phil_tenancy:
    shops:
        general:
            includes: [rose, tavern]
        rose: ~
        tavern: ~
```

Chaque boutique est un locataire, avec son propre catalogue et son propre panier. Une boutique générale qui inclut
d'autres boutiques montre leurs catalogues à côté du sien et encaisse le paiement de toutes à sa caisse unique. Chaque
boutique incluse garde sa propre boutique sur son propre hôte. `Domain\Shop\ShopNetwork` tient ces liens, et refuse à la
compilation :

- une boutique qui s'inclut elle-même ;
- une boutique listée deux fois dans le même `includes` ;
- une boutique qui est incluse et en inclut d'autres : l'inclusion n'a qu'un niveau de profondeur.

| Méthode de `ShopNetwork` | Retourne |
|---|---|
| `catalogues(TenantId $shop)` | Le catalogue propre de la boutique d'abord, puis ceux qu'elle inclut, dans leur ordre déclaré |
| `sells(TenantId $shop, TenantId $catalogue)` | Si un produit de ce catalogue peut aller dans le panier de cette boutique |
| `includedBy(TenantId $catalogue)` | Les boutiques qui incluent celle-ci |
| `sharesAccounts(TenantId $one, TenantId $other)` | Si un compte client connecté sur l'une l'est sur l'autre : la même boutique, une boutique et celle qu'elle inclut, ou deux boutiques incluses par la même |

### La boutique de la requête

`Application\Shop\ShopContext`, un service public, lit le locataire actif contre le réseau. Il ne détient rien en propre.

| Méthode | Retourne |
|---|---|
| `current(): ?TenantId` | La boutique de la requête, ou `null` |
| `catalogues(): list<TenantId>` | Les catalogues qu'elle vend ; aucun hors d'une boutique |
| `sells(TenantId $catalogue): bool` | Si un produit de ce catalogue peut aller dans son panier ; jamais hors d'une boutique |
| `origin(list<TenantId> $catalogues = []): OrderOrigin` | De quoi marquer une commande passée maintenant ; `NoActiveTenant` hors d'une boutique |

### L'origine d'une commande

`Domain\Shop\OrderOrigin` est ce dont une commande est marquée : la boutique où elle a été passée (`$shop`) et les
catalogues distincts de ses lignes (`$catalogues`). Un catalogue que la boutique ne vend pas est refusé avec
`InvalidValue`. `spansCatalogues()` indique une commande payée à la caisse d'une boutique générale pour les produits
d'une autre boutique. Un back-office central liste les commandes de toutes les boutiques et sait toujours d'où chacune
vient.

```php
public function placeOrder(Cart $cart, ShopContext $shop): Order
{
    $origin = $shop->origin($cart->catalogues()); // the shop, and the catalogues of the cart's lines

    return Order::place($cart, $origin->shop->value, array_map(strval(...), $origin->catalogues));
}
```

### Ce que lit un bundle de commerce

| Besoin | Ce qu'offre le bundle |
|---|---|
| Ouvrir le catalogue et le panier de la boutique | `HostTenantResolved`, envoyé une fois le locataire de l'hôte entré ; `ShopContext::current()` |
| Lister les produits d'une boutique générale | `ShopContext::catalogues()` : les locataires dont il faut montrer les produits |
| Accepter un produit dans le panier | `ShopContext::sells()` |
| Marquer une commande de sa boutique | `ShopContext::origin()` et `OrderOrigin` |
| Garder le client connecté d'une boutique à la suivante | `ShopNetwork::sharesAccounts()`, appliqué par les sessions limitées : voir [authentication.md](authentication.md) |

Le filtre Doctrine garde chaque requête à l'intérieur d'un locataire. Une requête qui liste les produits de plusieurs
catalogues, comme la page de catalogue d'une boutique générale, s'exécute avec le filtre suspendu et nomme elle-même ses
locataires, comme le décrit [doctrine-filter.md](doctrine-filter.md) pour le travail entre locataires.
