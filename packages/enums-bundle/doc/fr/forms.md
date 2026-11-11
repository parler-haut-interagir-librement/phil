<!-- phil-dev source-sha256=e0fdfcc35d9e2af2c2c9429555b316edf2612e42b94da64c97b8886d6f5dd739 -->
# Formulaires

Le bundle étend le `EnumType` de Symfony. Sans ses options, un champ offre tous les cas, comme celui de Symfony ; avec
elles, il offre une [sélection](selection.md) :

```php
use Symfony\Component\Form\Extension\Core\Type\EnumType;

$builder->add('status', EnumType::class, [
    'class' => OrderStatus::class,
    'group' => 'customer',
    'except' => [OrderStatus::CANCELLED],
]);
```

| Option | Type | Valeur par défaut | Conserve |
|---|---|---|---|
| `group` | chaîne ou liste de chaînes | `[]` | Les cas qui appartiennent à au moins un de ces groupes. |
| `only` | liste de cas | `[]` | Les cas parmi ceux donnés. |
| `except` | liste de cas | `[]` | Les cas autres que ceux donnés. |
| `where_meta` | table de classe de méta-propriété vers valeur | `[]` | Les cas dont les méta-propriétés ont ces valeurs. |

Les choix conservent l'ordre de déclaration de l'enum. `choices` fonctionne toujours et accepte une `CaseCollection`
(`OrderStatus::inGroup('customer')`), comme tout itérable ; lorsqu'une des options ci-dessus est définie, elle remplace
`choices`.

Trois autres options organisent les choix plutôt que de les sélectionner :

| Option | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `without_deprecated` | bool | `false` | Exclut les cas marqués `#[\Deprecated]`, après la sélection. |
| `group_by_meta` | classe de méta-propriété, ou `null` | `null` | Place les choix dans des `<optgroup>` selon la première valeur de cette méta-propriété ; un cas sans cette valeur reste hors de tout groupe. |
| `preferred_group` | chaîne ou liste de chaînes | `[]` | Offre d'abord les cas de ces groupes, comme `preferred_choices` ; lorsqu'elle est définie, elle remplace `preferred_choices`. |

```php
$builder->add('status', EnumType::class, [
    'class' => OrderStatus::class,
    'without_deprecated' => true,
    'group_by_meta' => Group::class,   // <optgroup label="customer">, <optgroup label="staff">
    'preferred_group' => 'common',
]);
```

**`without_deprecated` est désactivée par défaut** et devrait le rester sur un formulaire qui modifie un
enregistrement : un enregistrement qui contient un cas obsolète n'afficherait aucun choix sélectionné, et sa sauvegarde
perdrait la valeur. Activez-la là où un enregistrement est créé. Les noms de groupe sont traduits comme les libellés de
groupe, dans le `choice_translation_domain` du champ.

## Libellés

Le libellé d'un choix est, dans l'ordre :

1. la clé, lorsque les choix sont donnés avec le libellé comme clé ;
2. le cas lui-même, lorsque l'enum est traduisible : Symfony le traduit ([Traduction](translation.md)) ;
3. le `Label` du cas ;
4. le nom du cas.

## Ensembles : EnumSetType

Un champ dont la donnée est un `EnumSet` de `phil/enums` : les cas sont des cases à cocher, et ceux de l'ensemble sont
cochés.

```php
use Phil\EnumsBundle\Infrastructure\Form\EnumSetType;

$builder->add('permissions', EnumSetType::class, ['class' => Permission::class]);
```

C'est un `EnumType` avec `multiple` et `expanded` activés, donc toutes les options ci-dessus s'appliquent : `group`,
`except`, `without_deprecated`, les libellés. `expanded: false` donne une liste à sélection multiple. Une soumission
vide donne l'ensemble vide, jamais `null`. Une donnée qui n'est pas un `EnumSet` de l'enum du champ fait échouer la
transformation du formulaire.

## Valider le même sous-ensemble

Un formulaire ne fait qu'afficher des choix ; une requête falsifiée peut quand même soumettre un autre cas. Validez la
propriété avec la contrainte `EnumCase` et les mêmes critères ([Validation](validation.md)), afin que le serveur accepte
exactement ce que le formulaire offre.
