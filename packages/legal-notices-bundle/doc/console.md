# Console

## phil:legal-notices:check

```sh
bin/console phil:legal-notices:check
```

Lists, one line each, what the public documents lack: a field of the identity a document requires
(`missing_field`), a body the renderer refuses (`unreadable_body`), a placeholder without a value
(`unknown_placeholder`). It exits with `1` when there is any, so that a deployment stops on it.

## phil:legal-notices:announce

```sh
bin/console phil:legal-notices:announce --days=1
```

Dispatches `Phil\LegalNotices\Event\DocumentPublished` for each version whose publication date falls in the last
`--days` days, 1 to 366, and prints it. It keeps nothing: run once a day by a scheduler, each version is announced
once. A listener tells the members, clears a cache or records the change.

```php
#[AsEventListener]
public function __invoke(DocumentPublished $event): void
{
    $this->bus->dispatch(new TellMembers($event->type, $event->version));
}
```
