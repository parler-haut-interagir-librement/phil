<!-- phil-dev source-sha256=d680eb34b9871a33e2f717221451186a05f2fa09ae0bd8dc6cb576e3b6d5de03 -->
# Référence

## Annoncer depuis une migration

| Type | Nature | Rôle |
|---|---|---|
| `AnnouncingMigration` | classe abstraite | Un `AbstractMigration` de Doctrine avec `note()` et `news()` |
| `Announcing` | interface | `announceWith(Announcements)` : ce qu'appelle la fabrique |
| `Announces` | trait | `announceWith()`, `note()` et `news()`, pour une classe de base propre à une application |
| `News` | `final readonly class` | Un titre, ses lignes et, éventuellement, une URL |
| `AnnouncementsMissing` | `LogicException` | Une migration a annoncé sans avoir reçu les annonces |

`note()` prend une ligne par locale (`['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']`) et `news()` un
`News` par locale. Une locale est une langue, éventuellement suivie d'une région : `fr` ou `fr_CA`. Une note est une
ligne de texte ; une nouvelle a un titre, et son URL est une URL ou rien. Tout le reste est un `InvalidAnnouncement`.

Une application qui a sa propre migration de base utilise le trait :

```php
abstract class SiteMigration extends AbstractMigration implements Announcing
{
    use Announces;
}
```

## Ce qu'annonce une exécution

| Type | Nature | Rôle |
|---|---|---|
| `Announcements` | `final class` | Les notes et les nouvelles d'une exécution, conservées par la migration qui les a laissées |
| `Release` | `final readonly class` | Ce qu'annonce l'exécution, dans l'ordre d'exécution des migrations ; `notesIn()` et `newsIn()` par locale |
| `AnnouncingMigrationFactory` | décore `MigrationFactory` | Donne les annonces à chaque migration `Announcing` |
| `AnnouncingMigrator` | décore `Migrator` | Annonce l'exécution une fois qu'elle est terminée |

Le migrateur vide les annonces au début d'une exécution, puis, une fois celle-ci terminée, garde les notes et les
nouvelles des migrations qui sont allées jusqu'au bout, ni sautées ni en échec, dans l'ordre du plan :

| L'exécution | Ce qui est annoncé |
|---|---|
| terminée | chaque migration allée jusqu'au bout |
| `--dry-run` | rien |
| échouée, en tout ou rien | rien : tout a été annulé |
| échouée, pas en tout ou rien | les migrations exécutées avant l'échec, qui restent |

Une note qu'apportent plusieurs migrations de l'exécution n'est annoncée qu'une fois dans chaque locale, à sa première
place ; les autres notes gardent leur ordre. Deux notes sont les mêmes quand elles ne diffèrent que par les espaces qui
les entourent : `'* Libraries updated. '` et `'* Libraries updated.'` ne sont annoncées qu'une fois. Une note écrite en
français et en anglais par une migration, et seulement en français par une autre, est annoncée une fois en français et
une fois en anglais. Une nouvelle n'est annoncée qu'une fois de la même façon, quand son titre, ses lignes, chacune
sans les espaces qui l'entourent, et son adresse sont les mêmes.

L'annonce vient après que Doctrine a validé, de sorte qu'une migration annoncée est une migration conservée.

## Canaux

| Type | Nature | Rôle |
|---|---|---|
| `Channel` | `final readonly class` | Où et comment une version est annoncée |
| `Content` | enum | `Notes` ou `News` |
| `Announcer` | `final readonly class` | Annonce une version sur chaque canal |
| `Publisher` | interface | Envoie un message sur un transport |
| `ChatterPublisher` | `final readonly class` | Publie par le chatter de `symfony/notifier` |

| Argument de `Channel` | Valeur par défaut | Rôle |
|---|---|---|
| `transport` | | Le transport de chatter, tel que le nomme `symfony/notifier` |
| `locale` | | La locale dans laquelle l'annonce est lue ; une note ou une nouvelle qui n'y est pas écrite est omise |
| `content` | `Content::Notes` | Les notes de version, ou les nouvelles |
| `title` | `''` | Le titre du premier message ; aucun s'il est vide |
| `continued` | `''` | Le titre des messages qui suivent ; aucun s'il est vide |
| `heading` | `'{title}'` | La ligne d'une nouvelle qui contient son titre |
| `link` | `'{url}'` | La ligne qui contient son URL ; aucune si elle est vide ou si la nouvelle n'a pas d'URL |
| `maxLength` | `2000` | Le message le plus long, en caractères : 2000 sur Discord ; au moins 100 |

Un titre occupe au plus la moitié d'un message. `{counter}` dans `title` ou `continued` devient ` (2/3)` quand la
version prend plus d'un message, et rien quand elle en prend un seul.

Un canal qui échoue est journalisé comme une erreur et abandonné ; les autres canaux sont quand même informés, et les
migrations, déjà exécutées, restent.

## Comment les messages sont coupés

Les lignes d'un canal sont réunies, une par ligne, tant que le message, titre et ligne vide compris, tient dans
`maxLength` ; la ligne suivante commence le message suivant. Une ligne trop longue à elle seule est coupée à sa dernière
espace qui tient, ou au milieu d'un mot quand il n'y en a aucune. La place qu'occupe un titre est comptée avec son
compteur, de sorte qu'une version de dix messages fait de la place pour ` (10/10)`. Les longueurs se comptent en
caractères, jamais en octets.
