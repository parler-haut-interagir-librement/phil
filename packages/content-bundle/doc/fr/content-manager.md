<!-- phil-dev source-sha256=65851e70699512fdf03616a9fd3aaefbce1279a101c655e681b3df81b000643c -->
# Écrire du contenu depuis PHP et depuis les migrations

`Phil\ContentBundle\Application\ContentManager` est l'interface PHP du bundle : ce qu'appellent les commandes de
console, et ce qu'appelle une application ou une migration Doctrine pour écrire du contenu. C'est un service, injecté
automatiquement par sa classe.

```php
use Phil\ContentBundle\Application\ContentManager;

final readonly class OpenTheSite
{
    public function __construct(private ContentManager $contents)
    {
    }

    public function __invoke(): void
    {
        $news = $this->contents->create('news', 'fr', [
            'templateKey' => 'news',
            'slug' => 'opening',
            'excerptTitle' => 'Opening',
            'bodySource' => 'The site **opens** today.',
        ]);
        $this->contents->publish($news, 'fr');
        $this->contents->edit($news, 'fr', ['excerptTitle' => 'Grand opening']);   // forks version 2
        $this->contents->removeDraft($news, 'fr');                                    // back to the published draft
        $this->contents->live($news, 'fr_CA');                                        // normalized, along the chain
        $this->contents->status($news);                                               // place, slug and versions per locale
        $this->contents->delete($news);                                               // rows, routes and root
    }
}
```

## Les méthodes

Une locale est une `Locale` ou sa forme en chaîne. Les données utilisent les clés de la forme normalisée
([pipeline.md](pipeline.md#données-soumises)) : une clé absente ne change rien, `null` efface.

| Méthode | Renvoie | Ce qu'elle fait |
|---|---|---|
| `create(string $resourceKey, Locale\|string $locale, array $data = [])` | `ContentReference` | construit une racine par `ContentRootFactory`, la vide vers la base (un identifiant généré par la base de données n'existe qu'à ce moment) et écrit son premier brouillon dans la locale, version 1, `unpublished` |
| `edit(ContentReference, Locale\|string, array $data)` | `void` | modifie le brouillon de la locale, en créant d'abord une nouvelle version si la version est figée ([publication.md](publication.md#modifier)) |
| `publish(ContentReference, Locale\|string)` | `void` | publie la locale |
| `unpublish(ContentReference, Locale\|string)` | `void` | met la locale hors ligne ; sa route reste |
| `removeDraft(ContentReference, Locale\|string)` | `void` | remet le brouillon à partir duquel la locale a été publiée en dernier |
| `delete(ContentReference)` | `void` | retire chaque ligne de la racine dans les deux étapes et toutes les versions, ses copies de travail, chacune de ses routes, actuelles et historiques, puis la racine : ses adresses redeviennent libres ; répartit `PageRetired` pour chaque locale qui était en ligne |
| `draft(ContentReference, Locale\|string)` | `array<string, mixed>` | le dernier brouillon de la locale, fusionné par-dessus sa ligne partagée et normalisé |
| `live(ContentReference, Locale\|string)` | `array<string, mixed>` | ce qui est servi à un lecteur de la locale, le long de sa chaîne de repli déclarée, normalisé |
| `list(string $resourceKey)` | `list<ContentReference>` | chaque racine du genre |
| `status(ContentReference)` | `list<ContentStatus>` | un état par locale de la dernière version de brouillon, trié par locale ; vide pour une racine sans brouillon |
| `single(string $resourceKey)` | `?ContentReference` | la racine d'un genre unique (`phil_content.singles`), `null` avant sa création ; un genre non déclaré unique donne une `InvalidContent` |
| `exists(ContentReference)` | `bool` | si la racine existe |
| `servedLocale(Locale\|string)` | `Locale` | la locale, une fois établi que `phil_content.locales` la sert |

`ContentStatus` est une valeur en lecture seule avec les propriétés publiques `reference`, `locale` (chaîne),
`draftVersion` (entier), `place` (`?string`), `slug` (`?string`) et `liveVersion` (`?int`, la version servie aux
lecteurs de la locale).

### Les erreurs

Chaque méthode qui prend une locale refuse, par `InvalidDimension`, une locale que `phil_content.locales` ne sert pas
ou qui est mal formée. Les refus du domaine implémentent tous `Phil\DddBundle\Domain\Exception\DomainError` :

| Exception | Quand |
|---|---|
| `InvalidDimension` | une locale mal formée ou non servie |
| `InvalidContent` | une valeur soumise refusée, une référence mal formée, une seconde racine d'un genre unique |
| `PublicationLosesContent` | la publication ferait perdre une partie du corps en UJO, et les pertes n'ont pas été acceptées |
| `ContentNotFound` | aucune racine pour la référence ; aucun brouillon ou aucune ligne en ligne là où il en faut une ; rien de publié le long de la chaîne |
| `SlugTaken` | le slug est l'adresse actuelle d'une autre racine du même genre dans la locale |
| `TransitionRefused` | le workflow ne permet pas l'opération depuis l'endroit où se trouve la ligne, une protection de l'application l'a bloquée, ou retirer le brouillon écarterait les changements partagés d'une autre locale |

Une clé de ressource inconnue lève `InvalidContentMapping`, une `LogicException` : c'est une erreur du code ou de la
configuration de l'appelant.

## Les transactions

**Chaque méthode d'écriture s'exécute dans sa propre `ContentTransaction`**, imbriquée dans celle de l'appelant quand
une est ouverte (un point de sauvegarde sur Doctrine). `draft()`, `live()`, `list()` et `status()` ne font que lire.

Sur Doctrine, un échec annule la transaction et vide l'unité de travail — les entités de l'appelant aussi, quand
l'opération est imbriquée dans la transaction de l'appelant. Un refus du domaine laisse le gestionnaire d'entités
ouvert. Un échec de la base de données pendant un vidage (un index unique qui refuse une publication concurrente) le
ferme, comme `doctrine/orm` le ferme chaque fois qu'un commit échoue : un appelant qui attrape l'erreur et écrit de
nouveau réinitialise d'abord le gestionnaire d'entités (`ManagerRegistry::resetManager()`). Voir
[storage.md](storage.md#transactions).

## Depuis une migration Doctrine

Une migration reçoit le gestionnaire par une fabrique de migrations de l'application, qui décore celle de
DoctrineMigrationsBundle :

```php
namespace App\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Phil\ContentBundle\Application\ContentManager;

final readonly class ContentMigrationFactory implements MigrationFactory
{
    public function __construct(
        private MigrationFactory $inner,
        private ContentManager $contents,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $migration = $this->inner->createVersion($migrationClassName);

        if ($migration instanceof ContentMigration) {
            $migration->setContentManager($this->contents);
        }

        return $migration;
    }
}
```

```yaml
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    services:
        Doctrine\Migrations\Version\MigrationFactory: App\Migrations\ContentMigrationFactory
```

`ContentMigration` est une interface propre à l'application (un mutateur `setContentManager()`), implémentée par les
migrations qui écrivent du contenu :

```php
final class Version20261102120000 extends AbstractMigration implements ContentMigration
{
    private ContentManager $contents;

    public function setContentManager(ContentManager $contents): void
    {
        $this->contents = $contents;
    }

    public function up(Schema $schema): void
    {
        $about = $this->contents->create('static_page', 'fr', ['slug' => 'a-propos', 'excerptTitle' => 'À propos']);
        $this->contents->publish($about, 'fr');
    }
}
```

Le gestionnaire écrit par l'ORM tout de suite, alors que les instructions `addSql()` s'exécutent après le retour de
`up()` : **une migration qui écrit du contenu a besoin que ses tables aient été créées par une migration
antérieure**, et n'écrit aucun `addSql()` elle-même. Dans la transaction de la migration, chaque appel s'imbrique dans
un point de sauvegarde.
