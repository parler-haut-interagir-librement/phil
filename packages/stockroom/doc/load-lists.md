# Load lists

An event takes things out of the stockroom: its load list says which. `Deployments` keeps one list per event and follows
it from the shelves to the event and back.

## Planning

```php
$deployments->plan('tavern-scene', [
    new LoadLine($swords, 12),
    new LoadLine($lanterns, 4, 'crate-3'),   // from that container
]);
```

A line names an item, a quantity, and optionally the container to take it from. Lines of the same item from the same
container are merged. A list holds at most 500 lines; its items and containers must exist. Planning again replaces the
list.

## Picking

`picking($event)` gives a `PickLine` for each line: the containers holding the item — its home first, then the others
in the order of their keys, or only the container the line names —, how many pieces are there in all, and whether the
line is short. An item that may not leave counts none.

## Deploying

`deploy($event)` sends the pieces still lacking at the event: what is there already counts, so deploying again sends
nothing more, and a deployment cut short is finished by the same call later. Each item is taken from the containers its
lines name, up to each line's quantity, then from its home and the others.

When the stockroom is short of something, nothing moves and the `Deployment` lists each `Shortfall`. With
`deploy($event, partial: true)`, what is there goes and the rest is listed. Every movement refers to the event.

## Checking at the return

```php
$check = $deployments->checkIn('tavern-scene', [$swords => 11, $lanterns => 4]);
$check->movements;  // the returns, to each item's home
$check->missing;    // one sword: expected 12, found 11
$check->surplus;    // what was counted beyond what was out
```

What was counted comes back to the item's home, or to the first container a line of the list took it from. What is
out and was not counted is listed missing and stays at the event, so that `outstanding($event)` still shows it; with
`checkIn($event, $counted, writeOff: true)`, it is recorded lost instead. An item counted that has nowhere to go stops
the check before anything moves. A count is from 0 to 1 000 000.

## The text format

`LoadListReader` reads a load list typed by hand, one line per item, and writes one back:

```text
# The tavern scene
12 x foam-sword
4 lantern @ crate-3
banner-red
```

A line is a quantity — one when it is left out —, an optional `x` or `×`, the code on the item's label, and optionally
`@` and the identifier of the container to take it from. Blank lines and lines starting with `#` are skipped.

```php
$reader = new LoadListReader($items);
$deployments->plan('tavern-scene', $reader->read($text));
$reader->write($deployments->loadList('tavern-scene')->lines);
```

The text is UTF-8 of at most 64 KiB and holds at most 500 lines. A line that is not one, or that names an item that
does not exist, is refused with its number: `Line 3 names the item "ghost", which does not exist.`. Whatever the text,
the reader returns lines or throws `InvalidStock`; what it writes reads back as the same lines.
