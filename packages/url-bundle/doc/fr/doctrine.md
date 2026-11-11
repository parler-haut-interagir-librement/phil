<!-- phil-dev source-sha256=2976ab173e450b3c3f90d0cef32673dbd5b9dcfb9c475bf77ac6f1724e5f8ac9 -->
# Doctrine

Avec DoctrineBundle, le type de colonne `phil_url` garde un `Url` normalisé dans un `VARCHAR(2048)` :

```php
#[ORM\Column(type: 'phil_url')]
public Url $website;
```

Une adresse et son genre sont deux colonnes que l'application mappe elle-même, le genre avec sa propre enum :

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
