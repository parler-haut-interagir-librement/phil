# Items and menus

## `MenuItem`

```php
MenuItem::of('games', 'menu.games')       // a key unique among its siblings, and a label
    ->withRoute('games')                   // or ->withUrl('https://…'): the one replaces the other
    ->withIcon('icon-dice')
    ->withDescription('Every game, from chess to go.')
    ->withCount(120, 4)                    // all of them, and the new ones
    ->withUpdatedAt(new DateTimeImmutable('2026-11-03'))
    ->withClass('card', 'wide')            // each class once
    ->withAttribute('data-sort', 'name')   // null takes an attribute away
    ->withChildren(MenuItem::of('chess', 'menu.chess')->withRoute('game', ['slug' => 'chess']));
```

An item is immutable: each `with…()` gives a new one. A key is up to 100 letters, digits, `_`, `-` and `.`; a label
is not blank; counts are zero or more, the new ones no more than all; children have different keys. Otherwise
`InvalidMenu` is thrown. `find()` reaches a descendant by key; `map()` changes the item's children, then the item:
an item whose children come back the same is passed on as it is, and the keys of the children are checked again only
when one of them was renamed.

The templates print an address in `href` and an attribute on the item's element, so both are checked when they are
set, whatever the builder read them from. An address is on the site — a path, `/games`, or a reference relative to the
page, `#top`, `?page=2` — or has one of the schemes `http`, `https`, `mailto` and `tel`; `javascript:`, `data:` and
any other scheme are refused, and so is an address with a space or a control character, which browsers drop from a
scheme before they read it. An attribute's name is a letter, then up to 99 letters, digits, `_`, `-`, `.` and `:`, and
does not start with `on`, as every event handler does.

`current` and `currentAncestor` are set by `Menus` — see [the current item](current.md) —; `isActive()` is either.

## `Menu`

```php
Menu::of('main', [$home, $games], translationDomain: 'messages');
```

A name, top items with different keys, and the translation domain of the labels — `null` shows them as they are.
`find()` reaches any item; `breadcrumb()` gives the items from the top to the current one; `map()` changes every
item, children first.
