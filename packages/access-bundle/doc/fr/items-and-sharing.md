<!-- phil-dev source-sha256=6559f909d83002f13693d2c841d471bcb03298611cd9f815fe303052d578add3 -->
# Éléments, visibilité et partage

## Éléments

`Application\Items` donne à un élément — une `ResourceReference` ou un `AccessibleItem` — son propriétaire, son espace
et sa visibilité :

```php
$items->register($page, MemberId::of('01JABC'));                          // private to its owner
$items->register($page, MemberId::of('01JABC'), Visibility::Members);      // in the site's space
$items->register($page, MemberId::of('01JABC'), Visibility::Members, 'club'); // in another space
$items->changeVisibility($page, Visibility::Everyone);
$items->transfer($page, MemberId::of('01JXYZ'));
$items->visibility($page); // ?Visibility
$items->owner($page);      // ?MemberId
$items->forget($page);     // every tuple of a deleted item
```

Enregistrer de nouveau remplace le propriétaire, l'espace et la visibilité.

## Visibilité

| Visibilité | Qui voit l'élément, en plus de son propriétaire et de ses partages | Tuple sur `view` |
|---|---|---|
| `Everyone` | chaque membre et chaque visiteur | `member:*`, `visitor:*` |
| `Members` | les membres de l'espace de l'élément, et ses administrateurs | `space:<id>#member` |
| `Admins` | les administrateurs de l'espace de l'élément | `space:<id>#admin` |
| `Owner` | personne d'autre | aucun |

Le propriétaire voit toujours son élément : il en détient le niveau le plus large. `Owner` est la valeur par défaut :
un site qui veut des éléments publics dès le départ le dit dans `default_visibility`.

Un lien entre deux éléments — une épingle de carte sur une page, une entrée d'une chronologie — ne montre que ce que
les deux extrémités permettent : `$a->linking($b)` donne la plus étroite de deux visibilités.

## Partage

`Application\Sharing` donne à un membre ou à un groupe d'accès un niveau de partage sur un élément :

```php
$sharing->share($page, 'edit', MemberId::of('01JABC'));
$sharing->share($page, 'view', $groups->group('supporters'));
$sharing->share($page, 'comment', MemberId::of('01JABC'), new DateTimeImmutable('2026-12-01 00:00 UTC'));
$sharing->unshare($page, 'edit', MemberId::of('01JABC'));
$sharing->shares($page); // list<Share>: level, member or group, end
```

Un partage à un niveau donne chaque niveau plus étroit. Partager de nouveau au même niveau remplace la fin. Un groupe
doit être déclaré dans la configuration.

## Accès qui se termine de lui-même

Un partage avec une fin, et l'adhésion à un groupe avec une durée, cessent de compter à cette seconde, selon l'horloge
de Symfony : aucune vérification, liste ou résumé ne les lit après, sans tâche planifiée. Une fin est postérieure à
maintenant, sinon elle est refusée. `phil:access:purge` supprime les tuples expirés plus tard.

## Espaces

`Application\Spaces` nomme les membres et les administrateurs de l'espace du site, ou d'un autre :

```php
$spaces->addAdmin(MemberId::of('01JADA'));
$spaces->addMember($groups->group('supporters'));  // every supporter is a member of the space
$spaces->isAdmin(MemberId::of('01JADA'), 'club');
```
