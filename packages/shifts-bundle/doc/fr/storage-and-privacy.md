<!-- phil-dev source-sha256=6f365e92e949b52f066cedaa116aedc75b444122af73a11d047326c63b5d409f -->
# Stockage et vie privée

## Les tables

| Table | Contient | Clé |
|---|---|---|
| `phil_shifts_shifts` | chaque quart : sa définition et ses places comme champs des formats de la bibliothèque, sa fenêtre, sa révision, l'instant de sa dernière écriture | `shift_id` |
| `phil_shifts_places` | une ligne par bénévole sur une liste, avec son état, réécrite avec la liste | `shift_id`, `volunteer` |
| `phil_shifts_swaps` | chaque offre du tableau d'échange comme champs de son format, avec son quart, ses bénévoles, son état et sa révision | `id` |
| `phil_shifts_guardians` | le contact du tuteur d'un bénévole mineur : nom, téléphone, courriel, qui l'a enregistré, et le consentement avec son instant et qui l'a enregistré | `volunteer` |
| `phil_shifts_absences` | chaque absence non annoncée : le quart, le bénévole, son instant et le coordonnateur qui l'a enregistrée, et sa levée | `shift_id`, `volunteer` |

Les instants sont des colonnes `datetime_utc_immutable`. Sur MySQL et MariaDB, les clés et les identifiants utilisent
la collation `utf8mb4_bin`, de sorte qu'ils se comparent octet par octet, comme sur PostgreSQL et SQLite. Avec l'ORM,
`ShiftsSchemaListener` ajoute les tables au schéma de la connexion configurée, et `doctrine:migrations:diff` en écrit la
migration. Sans l'ORM, une application les crée à partir du schéma que décrit le bundle :

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsTables;

foreach (ShiftsTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

## Les révisions

Chaque écriture d'une liste ou d'une offre est conditionnelle à la révision lue — `… WHERE shift_id = ? AND revision = ?`
—, que la base de données exécute de façon atomique. `Rosters` et `SwapBoard` relisent et appliquent de nouveau le
changement quand une autre requête est passée avant, trois fois au plus, puis refusent avec `shifts.busy`. Un échange
terminé écrit son offre et sa liste dans une seule transaction : ou bien la place est passée et l'offre est terminée, ou
bien ni l'un ni l'autre.

Les listes sont bornées : 500 quarts à venir au plus, 500 quarts d'un bénévole, 500 offres sur le tableau.

## Les événements

Une fois un changement stocké, ses événements — `VolunteerConfirmed`, `VolunteerReserved`, `InterestExpressed`,
`VolunteerWithdrew`, `ReservePromoted`, `PlaceTransferred`, `SwapOffered`, `SwapTaken` — vont au répartiteur
d'événements de l'application. Ils ne portent que des identifiants : un écouteur qui envoie un avis lit le quart et le
bénévole dans le magasin, ou transmet l'événement tel quel à un transport Messenger.

```php
use Phil\Shifts\Event\ReservePromoted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class TellThePromotedVolunteer
{
    public function __invoke(ReservePromoted $event): void
    {
        // $event->shiftId, $event->volunteerId
    }
}
```

## Messenger

`AllocateInterest` porte l'identifiant d'un quart. Son *handler* attribue les places libres parmi les intéressés ; un
quart supprimé, fermé ou qui n'est plus à attribuer est journalisé comme un avis avec son identifiant, et une liste
occupée trois fois est lancée en exception, de sorte que Messenger fait une nouvelle tentative du message.

```yaml
framework:
    messenger:
        routing:
            Phil\ShiftsBundle\Application\Message\AllocateInterest: async
```

## Les données d'un bénévole

Avec phil/privacy-bundle, `ShiftsPrivacy` exporte et efface les données d'un bénévole sous le module `shifts` :

- l'**export** contient les quarts où il figure — le quart, son titre, sa fenêtre et ses minutes, son état, depuis quand,
  et le coordonnateur qui l'a affecté —, les offres qu'il a faites ou prises, le contact de son tuteur avec le
  consentement, et ses absences non annoncées avec leur levée ;
- l'**effacement** le retire de chaque liste — personne n'est promu, aucun événement n'est réparti — et supprime ces
  offres, ce contact et ces absences. Les quarts restent pour les autres. Son rapport nomme les quatre tables :
  `phil_shifts_places`, `phil_shifts_swaps`, `phil_shifts_guardians` et `phil_shifts_absences`, chacune `Deleted`.

L'identifiant d'un bénévole est celui que donne l'application. Les seuls nom, numéro de téléphone et adresse de courriel
que les tables contiennent sont ceux du tuteur d'un bénévole mineur ; les dates de naissance restent à l'application.
