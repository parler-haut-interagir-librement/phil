# Position

| Type | Kind | Role |
|---|---|---|
| `Contract\Positionable` | interface | `position()`, `placeAt()`, `positionGroup()` |
| `PositionableEntity` | trait | The column `position`, an integer, nullable; one order for the table |
| `PositionListener` | listener: `onFlush` | Sends a record with no position to the end of its group |
| `Positions` | service | `moveBefore()`, `moveAfter()`, `moveFirst()`, `moveLast()` |
| `Exception\InvalidScope` | exception | A group the listener cannot compare, or a move across groups |

```php
#[ORM\Entity]
#[ORM\Index(columns: ['menu_id', 'position'])]
class MenuItem implements Positionable
{
    use PositionableEntity;

    #[ORM\ManyToOne(targetEntity: Menu::class)]
    private ?Menu $menu = null;

    public function positionGroup(): array { return ['menu']; }
}
```

```php
$positions = new Positions($manager);
$positions->moveBefore($item, $other);
$positions->moveLast($item);

$repository->findBy(['menu' => $menu], ['position' => 'ASC']);
```

Positions only order. A gap between two positions means nothing, and a removed record leaves one: the order of the
others stays the same.

## The group

`positionGroup()` names the fields whose values make a group; each group is ordered on its own. A field is a column of
the record, an enum column included, or a to-one relation it holds by one column, such as a parent or a tenant. A null
value is a group of its own, compared with `IS NULL`. The trait gives `[]`: one order for the whole table.

A to-many relation, a relation held by several columns, or a relation to a record whose identifier is itself a
relation raises `InvalidScope` at the flush that needs it.

## When a position is given

| Flush | Position |
|---|---|
| a record inserted without one | the end of its group |
| a record inserted with one | kept as given |
| a record updated after `placeAt(null)` | the end of its group |
| a record moved to another group, its position untouched | the end of its new group |
| a record moved to another group with a new position | kept as given |
| any other change | left alone |

The end of a group is one query, `MAX(position)`, on the table itself, so that rows the SQL filters hide still count.
Records of the same flush in the same group follow each other; a group whose relation points to a record of the same
flush, not stored yet, starts at 1. A subclass counts the table that holds the column.

## Moving

`Positions` moves a stored record within its group, in one transaction on the table itself:

| Method | Effect |
|---|---|
| `moveBefore($record, $other)` | `$record` takes the position of `$other`; `$other` and the records after it step one place down |
| `moveAfter($record, $other)` | `$record` takes the place after `$other`; the records after it step one place down |
| `moveFirst($record)` | `$record` takes the first position of the group; the others step one place down |
| `moveLast($record)` | `$record` takes the place after the last record of the group |

Each move locks before it reads, with a write that changes nothing, which holds on every platform: `moveBefore()` and
`moveAfter()` lock the row of `$other`, `moveFirst()` and `moveLast()` every row of the group. Two moves to the same
end of a group therefore wait for each other, and the second reads the position the first committed, instead of both
taking the same place; moves in different groups do not wait. A move to an end writes every row of its group once,
which the index below keeps to the group.

The listeners do not run, and soft-deleted, deactivated and filtered rows move with the others. A record moved against
itself, or alone in its group, stays where it is. A record placed against one of another group or table, a record not
stored yet, and a record whose group points to a record not stored yet raise `InvalidScope`: flush first.

The loaded records of the group follow the move in memory, so the next flush has nothing to write. A loaded record
whose position was changed and not flushed keeps that change, which the next flush writes. Flush a change of group
before moving the record: the move reads the group from the record in memory.

## Index

The application declares a plain index on the columns of the group and `position`, for the sort and for
`MAX(position)`. A unique index does not fit: a move shifts a range of positions in one statement, which a unique index
can refuse halfway on some platforms.
