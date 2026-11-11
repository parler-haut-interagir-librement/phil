<!-- phil-dev source-sha256=633ad1360de3e7fd37fb37443df4cd792f7d39bdeb12450c2d34a0147ee2e623 -->
# Formulaires et validation

## Le champ

`UjoType` est une zone de texte qui contient le JSON d'un document : la valeur que le script d'un éditeur par blocs y
écrit avant l'envoi du formulaire. À l'envoi, le JSON est lu et vérifié avec le service `Ujo` :

- un document valide devient le modèle : son JSON canonique par défaut, ou un `Phil\Ujo\Document` avec
  `document: true` ;
- un envoi vide devient `null` ;
- un document invalide rend le champ invalide, avec le message `This value is not a valid UJO document: {{ error }}`,
  où `{{ error }}` nomme le chemin et la raison, comme
  `The UJO document is invalid at blocks[0].data.level: 0 is below the minimum 1.`

```php
$builder->add('body', UjoType::class);                         // ?string, canonical JSON
$builder->add('body', UjoType::class, ['document' => true]);   // ?Document
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `document` | `false` | Si le modèle est un `Document` plutôt que son JSON canonique |
| `invalid_message` | `This value is not a valid UJO document.` | Le message de repli ; le message propre au champ, avec la raison, est utilisé d'abord |

Le champ est rendu comme un `textarea` ; le script de l'éditeur le masque habituellement et écrit le JSON à l'envoi.

## La contrainte

`ValidUjo` vérifie une valeur (du JSON UJO ou un `Document`) sur une entité ou un objet de données, peu importe ce qui
l'a remplie :

```php
use Phil\UjoBundle\Infrastructure\Validator\ValidUjo;

#[ValidUjo]
public ?string $body = null;

#[ValidUjo(types: ['paragraph', 'header', 'list', 'quote'])]
public ?string $comment = null;
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `types` | `null`, chaque type enregistré | Les types canoniques acceptés ; un bloc d'un autre type, blocs imbriqués compris, ajoute une violation par type |
| `message` | `This value is not a valid UJO document: {{ error }}` | Quand le document n'est pas valide |
| `typeMessage` | `Blocks of type {{ type }} are not accepted here.` | Quand un type n'est pas accepté |
| `level` | `Validation::Shape` | Ce que l'on vérifie du document : sa forme, à chaque enregistrement, ou `Validation::Publication`, aussi ce qu'il lui faut pour être publié |

Un brouillon s'enregistre avec sa forme vérifiée ; la publication, la planification et l'envoi en relecture en
demandent davantage (un titre rempli, une liste avec des éléments). Deux contraintes dans deux groupes de validation
disent les deux :

```php
use Phil\Ujo\Validation;

#[ValidUjo]
#[ValidUjo(level: Validation::Publication, groups: ['publication'])]
public ?string $body = null;
```

```php
$violations = $validator->validate($page, groups: ['Default', 'publication']);   // before publishing
```

Le champ de formulaire ne vérifie que la forme, pour qu'un brouillon puisse toujours être enregistré. Ce que vérifie
chaque niveau se trouve dans la documentation de la bibliothèque, sous les niveaux de validation.

`null` et le texte vide sont valides : ajoutez `NotBlank` pour exiger un document. Les codes de violation sont
`ValidUjo::INVALID_ERROR` et `ValidUjo::TYPE_ERROR`. Les messages sont traduits en anglais et en français, dans le
domaine `validators`.
