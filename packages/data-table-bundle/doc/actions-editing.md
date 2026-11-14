# Actions and editing

## Actions

```php
$table
    ->action(Action::row('archive', 'books.archive', 'BOOK_ARCHIVE', $this->archive(...))
        ->alsoOnSelection()
        ->confirmedBy('books.archive.question')
        ->destructive())
    ->action(Action::bulk('tag', 'books.tag', 'BOOK_TAG', $this->tag(...)));
```

A handler receives the rows, read again from the table's data source. An action asks for a confirmation unless
`withoutConfirmation()` says otherwise, and a browser without JavaScript always gets it. The question is a native
`<dialog>`, modal with JavaScript and shown in the page without it; see [Accessibility](accessibility.md). A handler throws
`ActionRefused` with a translation key to tell the person why it stopped.

## Inline editing

```php
Column::text('title', 'books.title')->editable(new Editing(
    'BOOK_EDIT',
    function (Book $book, string $title): void {
        $book->rename($title);
        $this->entities->flush();
    },
    [new NotBlank(), new Length(max: 200)],
));
```

The value reaches the function typed by its column: a string, a number, a `DateTimeImmutable`, a `bool`, a backed enum's
case or a choice's value. An emptied number, date or enum is `null`. Links and blocks cannot be edited.

When the table declares `version()`, an edit starts from the row's version and is refused when the row has moved on. The
current value then shows again with a message. The function may also throw `EditConflict`, around an
`OptimisticLockException` for instance, or `ActionRefused`.

When a sort, a filter or another page takes the row being edited off the page, the edit stays in the address and the
live region says that the row is no longer on the page; the control comes back with the row.

## What is checked at every call

| Check | Action | Edit |
|---|---|---|
| The table's attribute, when it has one | yes | yes |
| Stateless CSRF: the token `phil_data_table` and the browser's origin | yes | yes |
| The action or the column is declared, for this many rows | yes | yes |
| The rows belong to the table's data source | yes | yes |
| The voter attribute, on each row | yes | yes |
| The row's version | | yes |
| The value's type, then Symfony Validator | | yes |

Showing a button is not a permission: buttons are hidden when the voter refuses, and the check runs again when the call
comes. A plain form also carries the table's context with a seal made from `kernel.secret`, and the page it comes back to
must be a path of this site.

When a plain form's change stops, the template `@PhilDataTable/table/stopped.html.twig` says why, with a link back to the
table. Override it under `templates/bundles/PhilDataTableBundle/table/` to dress it.
