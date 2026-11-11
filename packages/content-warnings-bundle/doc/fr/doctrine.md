<!-- phil-dev source-sha256=c27d6ed645307c395588fe4dae89ad924347ea6792fdba2a4ebb6bd672ecdb88 -->
# Doctrine

Le bundle enregistre le type de colonne `phil_content_warnings` lorsque DoctrineBundle est activé. Il conserve un
`ContentWarnings` dans une seule colonne JSON, tel que l'écrit `ContentWarnings::toArray()`, et fonctionne sur n'importe
quelle entité :

```php
use Phil\ContentWarnings\ContentWarnings;

#[ORM\Column(type: 'phil_content_warnings', nullable: true)]
private ?ContentWarnings $warnings = null;
```

```json
[{"theme":"gore","severity":"severe","note":"The final battle"},{"theme":"spiders"}]
```

`null` est stocké en `NULL` SQL, et l'absence d'avertissement en `[]`. Une valeur qui n'est ni null ni un
`ContentWarnings` est refusée avec l'`InvalidType` de Doctrine. Une valeur stockée qui n'est pas du JSON, qui s'imbrique
plus profondément qu'un avertissement ou que `ContentWarnings::fromArray()` refuse lève le `ValueNotConvertible` de
Doctrine, avec la cause jointe.

Les avertissements désignent les thèmes par leur clé : un thème retiré du catalogue reste dans la colonne, et
l'affichage se rabat sur sa clé.
