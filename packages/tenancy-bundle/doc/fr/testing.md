<!-- phil-dev source-sha256=6965985638849fe31d47d71278208f66f3b3f165f77ec085ee462a5f5e62d5ba -->
# Tests

## Comment le bundle est testé

Les tests unitaires se trouvent dans `tests/Unit`. Aucun ne démarre de noyau ni n'ouvre de connexion à une base de
données.

| Domaine | Ce qui est couvert |
|---|---|
| `TenantId` | Les formes acceptées, la limite en octets, chaque refus (vide, trop long, UTF-8 invalide, espaces autour, espaces Unicode comprises, caractères de contrôle C0, DEL et C1), l'égalité sensible à la casse |
| `ReachableTenants` | `none()`, l'appartenance, l'ordre conservé, la liste tirée d'une décomposition à clés, `single()`, les doublons refusés |
| `ActiveTenantRule`, `Resolution`, `NoActiveTenant` | Chaque ligne de la table de résolution, la règle du changement, les exceptions et leurs messages |
| `ActiveTenant` | Un choix atteignable conservé, un choix périmé oublié, un changement mémorisé, un changement refusé qui laisse le choix intact, `leave()` |
| `TenantScoped` | Le champ par défaut, le champ vide refusé |
| `ActiveTenantFilter` | Les entités non marquées laissées tranquilles ; les colonnes de champ et d'association ; la mise entre guillemets par la connexion ; `1 = 0` sans locataire ; les deux erreurs d'association |
| `TenantContext` | L'entrée et la sortie atteignent chaque activation ; `require()` ; `within()` revient au locataire précédent, ou à aucun, même quand le travail lance une exception |
| `DoctrineTenantActivation` | Le locataire réglé sur chaque filtre activé, les filtres désactivés laissés tranquilles, un filtre neuf à la sortie, rien sans Doctrine |
| `SessionActiveTenantStore` | La session de la requête principale, rien de lu sans témoin de session, une valeur mal formée abandonnée, aucune session où mémoriser |
| `ResolveActiveTenant` | Chaque issue, le `target` sur les requêtes sûres seulement, les routes non résolues, une requête déjà dans un locataire, les sous-requêtes ; après un pare-feu de remplacement enregistré bien plus bas que sa place habituelle |
| `ConfiguredHostTenants`, `ResolveHostTenant` | Les hôtes lus en minuscules et sous leur forme ASCII, chaque hôte refusé, un hôte listé deux fois ; le locataire entré et annoncé, un hôte sans locataire, les sous-requêtes ; l'avertissement sans hôtes de confiance, une fois par processus |
| `TenantCacheNamespace` | Les références lisibles, les références mises en empreinte et leur vecteur de test, deux références qui se ressemblent, le préfixe d'étiquette, les noms réservés |
| `TenantScopedPool`, `TenantPools` | Sur deux locataires et l'espace de noms global : la même clé, les contrats de cache, la suppression, le vidage d'un locataire et du pool entier, les enregistrements différés validés dans leur propre locataire au travers d'un changement, d'une sortie et d'une réinitialisation, rien de différé dans l'espace de noms global qui atterrit dans celui d'un locataire, les sous-espaces de noms, l'élagage, les pools gardés et le plus ancien abandonné |
| `TenantScopedTagAwarePool`, `TenantTags` | Au-dessus d'un pool limité et en limitant un pool à étiquettes : la même étiquette invalidée dans un locataire et gardée dans l'autre et dans l'espace de noms global, les versions d'étiquettes gardées en mémoire, les contrats, les métadonnées sans le préfixe, un élément enregistré de nouveau, les étiquettes réservées refusées, les enregistrements différés, les sous-espaces de noms |
| `TenantCachePass` | `cache.app`, `cache.app.taggable`, les pools construits dessus, un pool à étiquettes de l'application, un `cache.app` nativement à étiquettes ; les pools partagés de Symfony, les pools globaux et les pools sur d'autres adaptateurs laissés tels quels ; les boucles de parenté ; un pool qui ne peut pas recevoir de sous-espace de noms, un pool global qui n'est pas un pool |
| `ShopNetwork`, `OrderOrigin`, `ShopContext` | Les catalogues de chaque boutique, ce qu'elle vend, les boutiques qui en incluent une autre, les comptes partagés, chaque réseau refusé ; les catalogues distincts d'une commande, un catalogue que la boutique ne vend pas ; rien de vendu hors d'une boutique |
| `ConfiguredAccountSharing`, `RememberSignInTenant`, `TenantScopedUserProvider` | Les groupes et les liens entre boutiques, jamais une requête hors de tout locataire, les sessions partagées ; le locataire mémorisé à la connexion et oublié à la déconnexion ; un compte rafraîchi sur son locataire, refusé sur un autre, sur aucun et avec une valeur mémorisée mal formée, partagé ; une session ouverte hors de tout locataire refusée sur un locataire, acceptée avec les sessions partagées ou quand les locataires atteignables décident |
| `ConfiguredTenantMailSettings`, `TenantTransports` | L'expéditeur de chaque sorte de locataire, chaque adresse refusée ; les en-têtes ajoutés, ceux que porte le message conservés, les messages bruts et les messages hors de tout locataire laissés tels quels |
| `TenantStamp` | La référence seulement, une référence qui ne peut pas être un locataire refusée |
| `SwitchActiveTenant` | Un locataire atteignable mémorisé, les cibles jamais suivies hors du site, les locataires inatteignables et mal formés refusés, la vérification de même origine |
| `CurrentTenantValueResolver`, `TenantMiddleware`, `TenantOption`, `TenancyExtension` | Leurs règles, une à une |
| `PhilTenancyBundle` | Chaque service, argument et étiquette qu'enregistre chaque configuration ; le filtre ajouté au début de DoctrineBundle, ou non |

Le filtre est exercé sur des `ClassMetadata` construites à la main et un *stub* de gestionnaire d'entités dont la
connexion met les valeurs entre guillemets. Ses tests unitaires n'ont donc besoin d'aucune base de données.

Les tests fonctionnels, dans `tests/Functional`, démarrent un vrai noyau (`App\TestKernel` : FrameworkBundle avec une
session *mock* et un transport Messenger en mémoire, DoctrineBundle sur SQLite, et le bundle configuré avec deux
adaptateurs qui lisent des en-têtes de requête). Des requêtes prouvent chaque issue de la résolution de bout en bout
(les lignes que retourne l'ORM, la redirection vers le sélecteur, un choix périmé oublié, rien de visible sans
locataire, aucun locataire transporté à la requête suivante), le changement, `#[CurrentTenant]` (jamais un attribut de
requête du même nom), le stockage en session par un navigateur qui garde son témoin, un message traité dans le
locataire où il a été réparti, une commande lancée avec `--tenant`, chaque clé de configuration, le filtre hors
d'une requête, y compris `suspend()` et `restore()`, et les pools de cache de l'application limités au locataire — la
même clé et la même étiquette dans deux locataires, un pool global, la limitation désactivée. Un second noyau (`Sites\SitesKernel` : FrameworkBundle,
SecurityBundle et le bundle, quatre hôtes de boutiques qui partagent un témoin de session, une file pour le courrier)
prouve les hôtes, le réseau de boutiques, un compte connecté sur une boutique et sur les boutiques qui lui sont liées
mais sur aucune autre — règle `access_control` comprise —, et le courrier de chaque boutique envoyé à sa façon par le
*worker* de la file, dans un seul conteneur gardé d'une requête à l'autre comme le garde un serveur de longue durée.
Lancez-les avec
`vendor/bin/phpunit packages/tenancy-bundle/tests/Functional`, sur un autre serveur avec
`PHIL_TENANCY_TEST_DATABASE_URL`. Installés seuls, là où FrameworkBundle n'est qu'une dépendance de développement et
où Symfony ne branche aucun répartiteur d'événements, ils sont ignorés.

Le test d'intégration de la sécurité de ligne, dans `tests/Integration`, demande un vrai PostgreSQL : il démarre le
même noyau avec `row_level_security` activé, crée le schéma, applique les instructions qu'affiche
`phil:tenancy:rls:policies`, et fait passer la connexion à un rôle ordinaire, puisqu'aucune politique ne retient un
superutilisateur. Il prouve que le SQL natif ne voit aucune ligne sans locataire et seulement celles du locataire actif
avec lui, même filtre désactivé ; qu'une connexion rouverte reprend le locataire actif ; qu'une ligne d'un autre
locataire ne peut pas être écrite ; et que `phil:tenancy:rls:check` réussit, puis échoue pour un superutilisateur,
une table sortie de sa politique et une table absente. Il lit le serveur dans `PHIL_TENANCY_TEST_POSTGRESQL_URL`,
sinon dans `PHIL_TEST_POSTGRESQL_URL`, qu'affiche `phil-dev services env`, et il est ignoré sans l'une ni l'autre :

```bash
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' vendor/bin/phpunit packages/tenancy-bundle/tests/Integration
```

## Lancer les tests

### À partir de la racine du monodépôt qui incube le bundle

Les tests s'exécutent sur le `vendor/` de la racine :

```bash
vendor/bin/phpunit packages/tenancy-bundle/tests
```

`composer packages` installe le bundle seul dans un nouveau répertoire, avec `phil/ddd-bundle` tiré du monodépôt,
vérifie ses dépendances déclarées avec `composer-dependency-analyser` selon `composer-dependency-analyser.php`, puis
lance ses tests.

### Installé seul

```bash
cd packages/tenancy-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

`phil/ddd-bundle` doit pouvoir être résolu, par un dépôt `path` ou `vcs` tant qu'il n'est pas publié.
`doctrine/dbal` et `phpunit/phpunit` viennent de `require-dev`.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent le filtre Doctrine par instruction produite et par hiérarchie
d'héritage, l'analyse d'une référence de locataire, et une lecture par un pool limité au locataire comparée à la même
lecture sur le pool lui-même. Lancez-les à partir de la racine du monodépôt :

```bash
composer bench -- packages/tenancy-bundle/tests/Benchmark
```

## Tester une application construite sur le bundle

### Règles et service

Les règles sont des fonctions pures : testez l'usage qu'en fait l'application avec des valeurs.

```php
$resolution = ActiveTenantRule::resolve(TenantId::fromString('wholesale'), ReachableTenants::of(TenantId::fromString('retail')));

self::assertSame('retail', $resolution->tenant()->value);
self::assertTrue($resolution->staleChoiceDropped);
```

Pour du code qui utilise `ActiveTenant`, construisez-le sur deux doublures en mémoire :

```php
final class InMemoryActiveTenantStore implements ActiveTenantStore
{
    public int $forgotten = 0;

    public function __construct(private ?TenantId $tenant = null)
    {
    }

    public function remembered(): ?TenantId { return $this->tenant; }
    public function remember(TenantId $tenant): void { $this->tenant = $tenant; }
    public function forget(): void { $this->tenant = null; ++$this->forgotten; }
}

final readonly class FixedReachableTenants implements ReachableTenantsProvider
{
    public function __construct(private ReachableTenants $reachable)
    {
    }

    public function reachable(): ReachableTenants { return $this->reachable; }
}

$activeTenant = new ActiveTenant(new InMemoryActiveTenantStore(), new FixedReachableTenants(ReachableTenants::of(TenantId::fromString('retail'))));
```

### Adaptateurs

- Vérifiez que l'adaptateur des locataires atteignables retourne `ReachableTenants::none()` dès que ses droits ne
  peuvent plus être servis. C'est la propriété d'échec en mode fermé sur laquelle repose tout le reste.
- Vérifiez que le `forget()` de l'adaptateur de stockage retire vraiment le choix.

### Isolement

Les tests du filtre prouvent le SQL qu'il ajoute. Ce que seule l'application peut prouver, c'est que ses entités sont
marquées : écrivez un test fonctionnel qui crée des lignes pour deux locataires, en active un, et vérifie qu'une
requête du dépôt ne retourne que les lignes de ce locataire, et aucune sans locataire actif. Lancez-le sur la base de
données qu'utilise l'application en production.

Dans un `KernelTestCase`, entrez dans le locataire par le contexte, comme le ferait une requête :

```php
$context = self::getContainer()->get(TenantContext::class);

$labels = $context->within(TenantId::fromString('retail'), fn (): array => $this->invoiceLabels());

self::assertSame(['R-1', 'R-2'], $labels);
self::assertSame([], $this->invoiceLabels());
```
