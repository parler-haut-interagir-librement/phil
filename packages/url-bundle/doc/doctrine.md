# Doctrine

With `doctrine/dbal` and DoctrineBundle, the column type `phil_url` keeps a `Url` normalised in a `VARCHAR(2048)`:

```php
#[ORM\Column(type: 'phil_url')]
public Url $website;
```

An address and its kind are two columns the application maps itself, the kind with its own enum:

```php
#[ORM\Column(type: 'phil_url')]
private Url $url;

#[ORM\Column(enumType: KnownUrlType::class)]
private KnownUrlType $kind;

public function link(): TypedUrl
{
    return TypedUrl::of($this->kind, $this->url);
}
```

The column type is the only part of the bundle that needs `doctrine/dbal`. Without it the bundle boots and leaves the
type out; `column_type: true` turns that absence into a refusal at container build, and `column_type: false` leaves the
type out even with Doctrine installed (see [installation](installation.md#column_type)).
