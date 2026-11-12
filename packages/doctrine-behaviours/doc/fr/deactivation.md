<!-- phil-dev source-sha256=8a1714fa6e210a31feafac7da65e9aa3dc911827c623afe93aa10e94b8ec3039 -->
# Désactivation

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Deactivatable` | interface | `isActive()`, `deactivatedAt()`, `deactivate()`, `reactivate()`, `stampDeactivated()` |
| `DeactivatableEntity` | trait | Les colonnes `active`, booléenne, et `deactivated_at`, `datetime_immutable`, nullable |
| `DeactivationListener` | écouteur : `prePersist`, `onFlush` | Garde la date accordée à l'état, en UTC |
| `DeactivatedFilter` | filtre SQL `phil_deactivated` | `active = true` sur chaque entité `Deactivatable` |

Un enregistrement désactivé est éteint, non supprimé : sa ligne reste entière, ses relations restent, et il revient d'un
seul appel. La suppression douce dit qu'un enregistrement n'est plus là, et une seconde suppression l'efface ; la
désactivation ne dit rien de tel, et les deux peuvent se combiner sur une même entité.

```php
$member->deactivate();
$manager->flush();                                 // active = false, deactivated_at = now

$manager->getFilters()->disable(DeactivatedFilter::NAME);
$member = $members->find($id);
$member->reactivate();
$manager->flush();                                 // active = true, deactivated_at = null
```

| Vidage | `deactivated_at` |
|---|---|
| une entité éteinte | mis à maintenant, sauf si une date lui a été donnée |
| une entité rallumée | oublié |
| une entité persistée déjà éteinte | mis à maintenant, sauf si une date lui a été donnée |

Une importation qui garde une date d'origine appelle elle-même `stampDeactivated()`, et l'écouteur la laisse en paix.

Tant qu'il est actif, le filtre cache les lignes désactivées de chaque requête, collections chargées à la demande
comprises. Un écran d'administration le désactive pour ses requêtes. Une relation vers un seul côté qui pointe vers une
ligne désactivée rencontre ce que rencontre une relation vers une ligne supprimée en douceur : voir
[Suppression douce](soft-delete.md#relations-vers-une-ligne-supprimée).

Le filtre compare la colonne avec la graphie du vrai propre à la base : `1` sur MySQL et SQLite, `true` sur PostgreSQL.
