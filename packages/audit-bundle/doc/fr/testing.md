<!-- phil-dev source-sha256=6726fb28c61cb99300c2ae4e5364f91786bd6b6b261a0969558f32a1cf9491df -->
# Tests

## Comment le bundle est testé

Des tests unitaires sous `tests/Unit/`, dans l'espace de noms `Phil\AuditBundle\Tests\`, avec PHPUnit 13 et
`#[CoversClass]` sur chaque classe de test. Aucun noyau; la table tourne sur SQLite en mémoire.

| Test | Ce qu'il prouve |
|---|---|
| `Domain\Commitment\CommitmentTest` | La construction est `HMAC-SHA256(key = salt, message = value)` avec une sortie binaire, fixée par le cas de test 1 de la RFC 4231; un engagement correspond seulement à sa valeur et à son sel; un engagement stocké fait l'aller-retour; tout ce qui ne fait pas 32 octets est refusé |
| `Domain\Commitment\SaltTest` | Un sel généré fait exactement 16 octets et deux sels diffèrent; un sel stocké de 16 octets ou plus est accepté, un sel de 15 est refusé |
| `Domain\Chain\ChainTest`, `Domain\Chain\FactsTest` | L'empreinte de la ligne, fixée ligne par ligne avec ses faits; un changement dans n'importe quel champ ou fait se voit; un saut de ligne stocké avant la charge utile rend une ligne non intègre; chaque refus d'un fait |
| `Domain\Chain\SiteSegmentTest` | Le nom d'un segment de site, son empreinte, sa base, ce qui n'en est pas un, et ses refus |
| `Domain\Read\QueryTest` | Chaque filtre d'une requête, la période en UTC, l'ordre d'un curseur, son jeton et ses refus, l'état d'un segment |
| `Application\SitesTest` | Chaque locataire chaîné séparément, la restauration d'un site, une ligne d'un autre site refusée, un locataire limité à ses propres segments, le site d'un segment |
| `Application\FeedTest` | Les faits envoyés et aucune charge utile; les emplacements par alimentation et par segment, les lots, les exécutions bornées, un lot en échec renvoyé, le *handler* de `AuditRecorded` |
| `Infrastructure\TenantIsolationTest` | Deux locataires et leurs sosies (`ACME`, `acmé`, `Acme`) sur une table dotée d'une collation qui ignore la casse et les accents comme le fait `utf8mb4_0900_ai_ci` : le lecteur et `AuditLog` ne gardent que les lignes du locataire, de l'acteur et de l'enregistrement demandés, les pages continuent au-delà des lignes écartées, une ligne se lit par son emplacement exact |
| `Application\TrailTest`, `Application\ReadSideTest` | La consignation, l'ancrage et la vérification; le locataire estampillé, le notificateur averti une fois; les pages, l'historique, l'export et le locataire imposé aux lectures; l'état de la chaîne |
| `Infrastructure\StorageTest`, `Infrastructure\LogReaderTest` | La table avec ses faits et ses index; le lecteur sur la table et le lecteur par balayage donnent les mêmes pages pour chaque requête, limite et ordre |
| `Infrastructure\ExportTest` | JSON Lines vers un fichier et vers un flux; la requête OTLP, son corps, ses refus et les redirections jamais suivies sur un client HTTP *mock*, le point de terminaison lu par `phil/url`; les emplacements dans leur table, déplacés vers l'avant seulement; `rowsAfter()` sur la table |
| `Infrastructure\AdaptersTest`, `Infrastructure\CommandsTest`, `PhilAuditBundleTest` | Les adaptateurs de Messenger, du locataire, de Security et de Workflow; les commandes, `--tenant` et `--max`; le câblage de chaque clé, l'alimentation et `ContextPass` |

Les deux ports que l'application écrit toujours, `CanonicalSerializer` et `AnchorWriter`, n'ont pas de test dans le
bundle : il n'y a rien à exécuter tant qu'une application ne les implémente pas.

Une suite fonctionnelle sous `tests/Functional/` démarre un vrai noyau, `tests/Functional/App/TestKernel.php`
(FrameworkBundle et le bundle sous un bloc `phil_audit` vide), avec son cache dans un répertoire qui lui est propre sous
le répertoire temporaire du système. `BundleRegistrationTest` fixe ce qu'une application voit du bundle : l'alias,
les clés qu'affiche `config:dump-reference phil_audit`, et l'échec de construction que cause une clé inconnue. Elle n'a besoin d'aucune base de données ni d'aucune variable d'environnement :
`vendor/bin/phpunit packages/audit-bundle/tests/Functional`.

Une suite d'intégration sous `tests/Integration/` exécute les tables de la piste et des alimentations sur MySQL 8 et
PostgreSQL 17 : `MySqlStorageTest` et `PostgreSqlStorageTest` consignent les lignes d'un site en UTF-8, les relisent
après une séquence, les paginent sur la table exactement comme le fait le lecteur par balayage, les vérifient, refusent
une place prise deux fois, et les envoient dans des alimentations dont les emplacements ne reculent jamais; ils vérifient aussi que le serveur
compare le locataire, l'acteur et l'identifiant de l'enregistrement octet par octet, de sorte que `acme`, `ACME`, `acmé`
et `Acme` sont quatre locataires. Chacun lit
l'URL de son serveur dans `PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est sauté sans elle; depuis la racine
du monodépôt, `phil-dev services up` démarre les serveurs et `phil-dev services test` exécute la suite contre eux.

### Depuis la racine d'un monodépôt qui installe le bundle par chemin

```bash
vendor/bin/phpunit packages/audit-bundle/tests
```

### Installé seul

```bash
cd audit-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

Le paquet ne fournit pas de `phpunit.xml`. `phil/ddd-bundle` doit pouvoir être résolu (à partir d'un dépôt `path` ou
`vcs` tant qu'il n'est pas publié).

Le paquet fournit `composer-dependency-analyser.php` pour
[`shipmonk/composer-dependency-analyser`](https://github.com/shipmonk-rnd/composer-dependency-analyser). Il comporte
une exception délibérée : `symfony/config` est déclaré mais importé nulle part, parce qu'`AbstractBundle` en a besoin au
chargement (voir [installation.md](installation.md)).

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent le vérificateur de chaîne sur des segments intacts d'au plus
50 000 lignes, dont le segment d'un site, et sur des segments réécrits avec jusqu'à 1 000 ancres perdues
(`ChainVerifierBench`); une page du journal lue sur la table de 50 000 lignes, la première et après 49 950 lignes,
entière et pour un seul acteur (`LogReaderBench`) : une page après un curseur coûte autant que la première; et un lot
de 500 faits envoyé depuis la table en JSON Lines, le premier et après 49 000 lignes (`FeedBench`) : un lot pris au
fond d'un segment coûte autant que le premier. Exécutez-les depuis la racine du monodépôt :

```bash
composer bench -- packages/audit-bundle/tests/Benchmark
```

## Le fuzzing

Deux cibles de fuzzing donnent des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/<target>` : `cursor-token`, où le curseur d'une page se réécrit exactement en le jeton qu'il a lu ou
est refusé avec `InvalidValue`, et `site-segment`, où un segment de site est construit ou refusé, et où celui qui est
construit ne nomme que son propre locataire, tient dans la table et redonne sa base. Depuis la racine du monodépôt :

```bash
composer fuzz -- cursor-token site-segment
```

## Tester vos adaptateurs

### `CanonicalSerializer` : des vecteurs de référence

Le sérialiseur est une porte à sens unique : son test est donc un ensemble d'entrées fixes et des octets exacts
attendus pour chacune, validés avec la classe et jamais régénérés :

```php
final class LengthPrefixedSerializerV1Test extends TestCase
{
    public function testTheVersionIsFrozen(): void
    {
        self::assertSame('lp-v1', new LengthPrefixedSerializerV1()->version());
    }

    public function testTheBytesAreFrozen(): void
    {
        self::assertSame(
            '1:a3:s781:b1:n1:c2:i3',
            new LengthPrefixedSerializerV1()->serialize(['c' => 3, 'a' => 'x', 'b' => null]),
        );
    }

    public function testFieldOrderDoesNotMatter(): void
    {
        $serializer = new LengthPrefixedSerializerV1();

        self::assertSame($serializer->serialize(['a' => 1, 'b' => 2]), $serializer->serialize(['b' => 2, 'a' => 1]));
    }

    public function testACommitmentIsSerializedLosslessly(): void
    {
        $commitment = Commitment::of(Salt::generate(), 'value');

        self::assertStringContainsString(bin2hex($commitment->bytes), new LengthPrefixedSerializerV1()->serialize(['email' => $commitment->bytes]));
    }
}
```

Un vecteur de référence qui échoue signifie que le changement doit devenir une nouvelle version, pas une attente mise à
jour.

### `AnchorWriter` : la destination refuse

La propriété qui compte n'est pas qu'une écriture réussisse, mais que **la suppression et l'écrasement échouent** avec
l'identifiant de connexion de l'adaptateur. Testez, contre le vrai stockage dans un compartiment de test :

1. une écriture réussit et l'objet se relit avec les mêmes empreintes;
2. la suppression de l'objet avec l'identifiant de connexion de l'adaptateur échoue;
3. son écrasement avec l'identifiant de connexion de l'adaptateur échoue ou produit une nouvelle version pendant que
   l'original reste;
4. la rétention de l'objet ne peut pas être raccourcie.

Ce sont des tests d'intégration, hors de la suite unitaire.

### Les engagements dans votre modèle de ligne

Testez le contrat d'effacement sur une vraie ligne : après l'effacement, le texte en clair et le sel ont disparu, les
octets de l'engagement sont inchangés, et l'empreinte de la ligne recalculée avec votre sérialiseur est égale à
l'empreinte stockée.

Installé seul, la suite fonctionnelle est ignorée : comme `symfony/event-dispatcher` n'est exigé qu'en développement,
le conteneur de Symfony laisse de côté le répartiteur d'événements et aucun noyau ne démarre. Elle s'exécute depuis une
application qui exige un noyau HTTP, comme la racine du monodépôt.
