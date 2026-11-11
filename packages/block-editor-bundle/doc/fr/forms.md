<!-- phil-dev source-sha256=8d486e066f8f9f1c42464b76b4ab0685168cfb636e1f40645a4e87ec7e034754 -->
# Formulaires

## Le champ

```php
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;

$builder
    ->add('body', BlockEditorType::class, [
        'tools' => ['paragraph', 'header', 'list', 'quote', 'image', 'toggle'],
        'placeholder' => $translator->trans('article.body.placeholder'),
    ])
    ->add('summary', BlockEditorType::class, ['preset' => 'reduced', 'required' => false]);
```

Le modèle est, par défaut, le JSON canonique UJO, une chaîne ; avec `document: true`, c'est un `Phil\Ujo\Document`. Un
éditeur vide ne soumet rien : la valeur du champ est `null`, et `NotBlank` s'applique donc.

| Option | Valeur par défaut | Effet |
|---|---|---|
| `preset` | le jeu d'outils configuré | `full` ou `reduced` |
| `tools` | ceux du préréglage, ou ceux configurés | Les types de blocs offerts |
| `inline_tools` | ceux du préréglage, ou ceux configurés | Les outils en ligne offerts |
| `read_only` | `false` | Le document est affiché, pas modifié ; un champ désactivé est aussi en lecture seule |
| `placeholder` | celui de l'éditeur | L'indication d'un paragraphe vide, déjà traduite |
| `locale` | celle de la requête | La langue des libellés de l'éditeur |
| `messages` | `[]` | Libellés remplacés, par clé ([javascript.md](javascript.md)) |
| `colors` | ceux configurés | Les jetons de couleur de l'outil de couleur du texte |
| `upload_url`, `mentions_url`, `links_url` | les routes du bundle, quand elles sont configurées | Les points de terminaison ; `null` en désactive un |
| `keep_foreign_blocks` | `false` | Les blocs de types hors du jeu d'outils, déjà présents dans le document, sont conservés plutôt que refusés |
| `document` | `false` | Le modèle est un `Document` plutôt que du JSON |

Un outil inconnu lève `InvalidToolset` à la construction du formulaire : c'est une erreur dans le code, pas dans la
saisie.

## Ce que le serveur vérifie

L'éditeur est une commodité ; la requête qu'il envoie n'est pas digne de confiance. À la soumission, le JSON passe deux
fois par `phil/ujo` :

1. le `Ujo` de l'application — le service de `phil/ujo-bundle` — le lit strictement, dans ses limites de taille et de
   profondeur, vérifie chaque bloc selon son type et le met en forme canonique ;
2. le normaliseur du jeu d'outils le restreint : un type de bloc que le jeu d'outils omet fait échouer le champ, sauf si
   `keep_foreign_blocks` est activé ; un élément en ligne qu'il omet est retiré, son texte étant conservé.

Un échec s'affiche sur le champ sous la forme `This value is not a valid UJO document: …`, avec le message de la
bibliothèque qui nomme le chemin, comme le fait `UjoType` de `phil/ujo-bundle`. Pour restreindre davantage les types, ou
pour vérifier un document défini par le code, ajoutez la contrainte `ValidUjo` de `phil/ujo-bundle` à la propriété.

## Blocs étrangers

Un document peut contenir des blocs que l'éditeur ne crée pas — un bloc de caractéristiques, une note de bas de page, un
bloc brut —, écrits par un autre outil ou convertis d'un autre format. L'éditeur affiche chacun comme une carte qui
nomme son type : on peut le déplacer, le dupliquer et le supprimer, jamais le modifier, et il est réécrit sans
changement. Avec la valeur par défaut `keep_foreign_blocks: false`, un tel document fait échouer le champ quand le type
est hors du jeu d'outils. Activez l'option là où les documents viennent d'ailleurs.

## Le thème de formulaire

`@PhilBlockEditor/form/block_editor.html.twig` dessine le widget — un champ caché qui contient le JSON, l'élément de
l'éditeur avec sa configuration dans un seul attribut `data-…-config-value`, et une zone dynamique polie — et donne au
libellé un identifiant auquel l'éditeur renvoie avec `aria-labelledby`. La rangée, les erreurs et l'aide viennent du
thème de formulaire de l'application. Pour dessiner l'éditeur ailleurs, produisez le rendu du widget seul avec
`form_widget(form.body)`.

Sans JavaScript, le champ caché garde le document tel qu'il a été reçu : soumettre le formulaire ne change donc rien.
