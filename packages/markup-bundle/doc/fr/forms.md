<!-- phil-dev source-sha256=d76a2e11e3e1c0924ab3a8cdfdfceca22380b6c3fb3980a76ec0dbad1859c115 -->
# Formulaires

`Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType` est un choix du format dans lequel un texte est écrit,
enregistré lorsque Symfony Form est installé. Il offre les formats activés, dans l'ordre de `formats` dans la
[configuration](configuration.md), et son modèle est le nom du format, une chaîne : `markdown`, ou le nom d'un format
qu'apporte un complément.

```php
$builder
    ->add('format', MarkupFormatType::class)
    ->add('source', TextareaType::class);
```

Une valeur qui n'est pas un format activé n'est pas synchronisée, et le champ affiche l'`invalid_message` du formulaire.
Le type est un enfant du `ChoiceType` de Symfony : ses options (`expanded`, `placeholder`, `preferred_choices`,
`choices` pour offrir moins de formats) s'appliquent comme là-bas.

## Libellés

| Format | Libellé | Français |
|---|---|---|
| `markdown` | Markdown | Markdown |
| `html` | HTML | HTML |
| `bbcode` | BBCode | BBCode |
| `mediawiki` | MediaWiki | MediaWiki |
| `dokuwiki` | DokuWiki | DokuWiki |
| `creole` | Creole | Creole |
| `text` | Plain text | Texte brut |
| `ujo` | Block editor (UJO) | Éditeur par blocs (UJO) |

Les libellés sont traduits dans le domaine `phil_markup`, à partir du répertoire `translations/` du bundle ; une
application en remplace un dans son propre fichier `translations/phil_markup.<locale>.*`. Un format qu'apporte un
complément affiche le `label` de son entrée dans `added_formats`, traduit dans le même domaine à partir des fichiers du
complément ; à défaut, son nom.
