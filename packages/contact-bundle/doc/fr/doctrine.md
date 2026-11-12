<!-- phil-dev source-sha256=0992e4a0453e0a9be1cdc1d47c1d01cc227051fd4ac56eece6449d37a6589661 -->
# Doctrine

Avec DoctrineBundle, le bundle enregistre le type de colonne `phil_contact` : un `ContactCard` dans une seule colonne
JSON, tel que l'écrit `ContactCardArray` de `phil/contact`. Aucune table par propriétaire : une boutique, une personne,
une organisation conservent chacune leur fiche dans une colonne qui leur est propre.

```php
#[ORM\Column(type: 'phil_contact', nullable: true)]
private ?ContactCard $contact = null;
```

Une valeur qui n'est pas un `ContactCard` est refusée à l'écriture ; un texte stocké qui n'est pas du JSON, qui est
imbriqué sur plus de 8 niveaux ou qui n'est pas une fiche est refusé à la lecture.

## Les noms structurés

Le nom structuré d'une fiche est gardé dans la même colonne, sous la clé `structuredName` qu'écrit `ContactCardArray`.
Le type de colonne et le schéma SQL ne changent pas : aucune migration Doctrine n'est nécessaire. Une fiche enregistrée
sans nom structuré est relue sans nom structuré, son `name` restant son seul nom, et en reçoit un la prochaine fois
qu'elle est enregistrée avec un. Une application qui veut les parties des noms qu'elle garde déjà les demande dans le
formulaire de la fiche, avec `structured_name`, ou les remplit elle-même et enregistre de nouveau les fiches.
