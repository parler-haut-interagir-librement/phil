<!-- phil-dev source-sha256=5e1c55ade4c1b9315966647631af7d1674ad9906968bf44d290ffc46e3478cf2 -->
# Identifiant daté

| Type | Genre | Rôle |
|---|---|---|
| `Contract\DatedIdentifiable` | interface | `datedId()`, `assignDatedId()`, `datedIdTime()` |
| `DatedIdentifierEntity` | trait | La clé primaire `id`, une colonne `guid` |
| `DatedIdentifierListener` | écouteur : `prePersist` | Donne à un nouvel enregistrement un UUID v7 de l'heure de l'horloge |

L'identifiant est un UUID v7 de `symfony/uid` : 48 bits de millisecondes suivis de bits aléatoires. Les identifiants se
trient dans l'ordre de leur création, une clé primaire sur eux reste compacte, et tout outil qui lit un UUID lit
celui-ci, sans format maison à documenter ni à analyser.

```php
$ticket->datedId();                                // '019a4f2e-…', known once persisted
$ticket->datedIdTime();                            // the moment it holds
```

Un identifiant donné à l'avance est gardé, et doit être un UUID v7 : `assignDatedId()` refuse tout le reste.

L'heure de création se lit dans l'identifiant : là où cette heure est elle-même sensible, utilisez un identifiant
aléatoire. L'écouteur doit être enregistré, puisque rien d'autre ne donne sa clé à l'entité.
