<!-- phil-dev source-sha256=51eeefde503f5302ebc6d4ceb8afd4e8dff39e8fdd4c1bb3c16658f898c79369 -->
# Genres acceptés

Un champ et une contrainte nomment les genres qu'ils acceptent sous l'une de ces formes, seule ou dans une liste :

| Forme | Accepte |
|---|---|
| `null` | Chaque genre international, `KnownUrlType::class` |
| Une classe d'enum, `RegistryUrlType::class` | Tous ses cas |
| Un cas, `KnownUrlType::Instagram` | Ce genre |
| Un `EnumSet` | Ses cas |

Les cas d'une même enum forment un ensemble ; les ensembles gardent l'ordre de leur première apparition, qui est
l'ordre dans lequel la détection les essaie ([phil/url](../../../url/doc/fr/kinds.md)). Les genres propres à
l'application passent avant les genres internationaux quand ils sont énumérés en premier :

```php
['types' => [RegistryUrlType::class, KnownUrlType::class]]
```

Toute autre forme lance une `InvalidArgumentException` quand le champ ou la contrainte est utilisé.
