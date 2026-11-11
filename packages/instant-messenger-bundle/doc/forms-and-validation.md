# Forms and validation

## The accepted services

A field and a constraint name the services they accept as `phil/url-bundle` names kinds of address: `null` for every
international service, an enum class for all its cases, a case, an `EnumSet`, or a list of these; cases of one enum
make one set, sets keep the order they first appear in.

## `HandleType`

Two fields, to and from a `Handle`: the service, chosen among the accepted ones — the choices in that order, named
by `label()` for the international services and by their case's name for the application's —, and the handle as the
person types it.

```php
$builder->add('messenger', HandleType::class, ['types' => [ForumMessenger::class, KnownMessenger::Signal], 'required' => false]);
```

An empty handle is `null`; a handle the chosen service does not read makes the field invalid with *This value is not
a valid handle on this service.* A handle is shown as people read it, `@adalove`, when its service reads that text
back to the same handle, and in its canonical form otherwise: an edit form submitted unchanged keeps its handle, even
for an application's service whose `display()` decorates it.

## `HandleOfType`

```php
#[HandleOfType([KnownMessenger::Signal, KnownMessenger::WhatsApp])]
public ?Handle $messenger = null;
```

A `Handle` is checked by its service; a text by detection among the accepted services. `null` and the empty text are
valid. The message, *This handle is on none of the accepted services: {{ types }}.*, has the code
`HandleOfType::NOT_ACCEPTED_ERROR`.

## Storing a handle

A handle is two columns the application maps, the service with its own enum:

```php
#[ORM\Column(enumType: KnownMessenger::class)]
private KnownMessenger $service;

#[ORM\Column(length: 255)]
private string $handle;

public function messenger(): Handle
{
    return Handle::fromCanonical($this->service, $this->handle);
}

public function setMessenger(Handle $messenger): void
{
    [$this->service, $this->handle] = [$messenger->type, $messenger->value];
}
```

The handle stored is the canonical one, read when it was kept. `Handle::fromCanonical()` takes it back as it is:
`Handle::of()` would read it again, and refuse a phone number that a later import of the numbering plans no longer
knows, so that the entity could no longer be loaded or shown.
