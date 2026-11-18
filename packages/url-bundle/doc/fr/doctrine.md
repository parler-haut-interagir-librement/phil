<!-- phil-dev source-sha256=6d9fbe71db57ed97ef40bed8c517e8adae591b600158878c574decddfbcb9435 -->
# Doctrine

Avec `doctrine/dbal` et DoctrineBundle, le type de colonne `phil_url` garde un `Url` normalisé dans un `VARCHAR(2048)` :

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

Le type de colonne est la seule partie du bundle qui a besoin de `doctrine/dbal`. Sans lui, le bundle démarre et laisse le
type de côté ; `column_type: true` transforme cette absence en refus à la construction du conteneur, et
`column_type: false` laisse le type de côté même avec Doctrine installé (voir [Installation](installation.md#column_type)).
