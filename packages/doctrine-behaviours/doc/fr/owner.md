<!-- phil-dev source-sha256=a59b82a2d7040574ae00ea3eef543aa4df0714f0ec6b95891fcd0a1d19adcbea -->
# Propriétaire

| Type | Genre | Rôle |
|---|---|---|
| `OwnerId` | valeur | Un identifiant stable d'au plus 255 octets, sans `@` |
| `Contract\Ownable` | interface | `ownerId()`, `claimOwner()`, `transferOwner()` |
| `OwnableEntity` | trait | La colonne `owner_id`, 255 caractères, nullable |
| `OwnerListener` | écouteur : `prePersist` | Donne à un nouvel enregistrement le propriétaire que nomme le résolveur |
| `Contract\OwnerResolver` | interface | `current(): ?OwnerId`, à qui appartiennent les nouveaux enregistrements en ce moment |
| `FixedOwner` | `OwnerResolver` | Toujours le même propriétaire, ou personne |
| `OwnerEraser` | service | Trouve et remplace un propriétaire dans chaque table qui en garde un |
| `Exception\InvalidOwner` | exception | Ce qui ne peut pas être un propriétaire |

Le propriétaire est la personne à qui appartient un enregistrement, là où l'auteur est celle qui l'a touché en dernier.
Il est fixé une fois, quand l'enregistrement est persisté, sauf s'il en a reçu un, et aucune modification ne le change.
Remettre un enregistrement à quelqu'un d'autre est la décision de l'application, `transferOwner()`.

## Un identifiant, pas une adresse courriel

Un propriétaire est copié dans chaque ligne que la personne possède et gardé aussi longtemps que la ligne : `OwnerId`
refuse donc, dès sa construction, tout ce qui contient un `@`. L'erreur ne répète jamais la valeur. Un résolveur nomme
les personnes par quelque chose de stable qui n'est pas un renseignement personnel, comme un UUID.

```php
new OwnerId('0192f0a0-0000-7000-8000-000000000001');   // fine
new OwnerId('ada@example.com');                        // InvalidOwner
```

Il n'y a volontairement aucun repli sur l'identifiant de connexion d'un compte : `SecurityOwnerResolver`, dans le
bundle, exige qu'une méthode soit nommée. Une ligne écrite avec un `@` par d'autres moyens fait lever `ownerId()` à sa
lecture.

## Demandes d'accès et d'effacement

`OwnerEraser` y répond sur chaque entité `Ownable` qui mappe le champ `ownerId`. Il travaille directement sur les tables
: les lignes supprimées en douceur et désactivées sont trouvées aussi, et aucun écouteur ne s'exécute. `tables()` liste
chaque table qui garde un propriétaire, trouvée ou non, pour un rapport qui nomme chaque lieu de stockage.

```php
$eraser->find($owner);                             // ['article' => 12, 'page' => 3]
$eraser->replace($owner, new OwnerId('former-81'));
$eraser->replace($owner);                          // nobody owns them any more
$eraser->tables();                                 // ['article', 'page']
```
