# ReactionPicker

The other reactions of `phil/reactions-bundle`: a disclosure of buttons grouped under their headings, which work with
no script. The picker controller of the bundle adds a search by label, the arrow keys, Home, End and Escape. It is
written inside the form that receives the buttons.

```twig {"preview":true}
<twig:ReactionPicker label="More reactions" :groups="[{label: 'Emojis', options: [{label: 'Like', emoji: '👍', image: null, imageAlt: null, attributes: {name: 'react', value: 'like'}}]}]" />
```

## Installation

::: installation

## Usage

```twig
<form method="post">
    <twig:ReactionPicker label="More reactions" :groups="groups" />
</form>
```

## Examples

### Translated texts

The texts are props; `{count}` in `resultsLabel` is replaced by the number of reactions shown.

```twig
<twig:ReactionPicker label="Autres réactions" :groups="groups" searchLabel="Chercher par nom" emptyLabel="Aucune réaction." resultsLabel="Réactions affichées : {count}" />
```
