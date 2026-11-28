# Selection

## Identifiers or a filter

```php
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;

// The ticked rows, each with the version shown on the page when the target keeps one.
$selection = Selection::identifiers(['p1', 'p2', 42], ['p1' => 7, 'p2' => 3]);

// Every item of the filter, less the rows the person unticked.
$selection = Selection::filter(FilterCriteria::of('status', ['status' => 'draft', 'category' => [3, 4]]), ['p9']);
```

- An identifier is 1 to 191 bytes without control character; an integer is kept as its decimal string. Duplicates are
  dropped and the order is kept.
- A version must belong to a selected item. When a selection carries one, the first attempt of the run checks it.
- A selection lists 10,000 identifiers at most, whatever the limits; `Limits::maxIdentifiers` lowers that.

## Filters

A filter is a name the repository knows, with up to 32 parameters, each a scalar, `null` or a list of up to 1,000
of them, 16 KiB at most once encoded. The parameters are sorted by name, so the same filter is stored the same way.

The repository says whether it accepts a filter (`supports()`), how many items match it (`count()`) and which ones,
chunk by chunk after a cursor (`matching()`). The library asks `supports()` at the selection, the preview, the
confirmation and before each chunk: a filter the repository no longer accepts stops the run. The identifiers are read
when the run executes, so an item that left the filter after the confirmation is not changed, and an unticked row
stays out.

The order of `matching()` must be stable, such as the primary key's: the cursor is the last identifier of the previous
chunk. A repository that gives back only identifiers already read is taken to have reached the end of the filter.

## The token

```php
$token = $selector->select($user->getUserIdentifier(), 'products', 'edit', $selection, new DateTimeImmutable());
// Put $token->value in the page; never the identifiers.
```

The token is 32 random bytes in base64url, 43 characters. The store files the selection under its SHA-256
fingerprint, so the store does not hold the token itself. A token is valid for `Limits::lifetimeSeconds`, for the person
who made the selection only, and once: the confirmation takes it. A token unknown, already used, expired, held by
another person or malformed throws `SelectionUnavailable`, with the same message in every case.

`SelectionToken::fromString()` reads what the page sends back.

## One box per field

A bulk edit form shows each field with a box that says whether the field changes:

```html
<label><input type="checkbox" name="apply[price]" value="1"> Change the price</label>
<input type="number" name="values[price]">
```

```php
use Phil\BulkActions\Change\ChangeSet;

$changes = ChangeSet::fromSubmission($request->request->all('values'), $request->request->all('apply'));
```

A box ticked by `"1"`, `"on"`, `"true"` (in any case) or `true` takes the field's value, `null` when the field is not
sent. Any other box leaves the field unchanged. `has()` tells a field set to `null` from a field left alone, and
`value()` gives the value. A change sets 64 fields at most.
