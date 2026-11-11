<!-- phil-dev source-sha256=b0d7f0a4f8c8b85bf514342a52d94cb5a5dedc54b6106c316bc8d7bbf8a10277 -->
# Doctrine

Avec DoctrineBundle, le bundle enregistre le type de colonne `phil_phone` : un `PhoneNumber` dans un `VARCHAR(33)`, en
E.164 avec son poste écrit à la façon de la RFC 3966, `+15145550123;ext=12`. Trente-trois caractères contiennent le
plus long numéro qu'accepte `PhoneNumber::parse()` : `+`, un indicatif de pays de trois chiffres, un numéro national
de dix-sept, `;ext=` et un poste de sept. Une colonne créée en `VARCHAR(32)` est élargie par la prochaine migration
que génère Doctrine.

```php
#[ORM\Column(type: 'phil_phone', nullable: true)]
private ?PhoneNumber $phone = null;
```

Une valeur qui n'est pas un `PhoneNumber` est refusée à l'écriture ; un texte stocké qui n'est pas un numéro est
refusé à la lecture.
