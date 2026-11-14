# ReactionButton

One reaction of `phil/reactions-bundle`: a button that says it is pressed, its emoji or its image, its label for screen
readers and its counter; or the same, as plain text, for a viewer who cannot press it. Any other attribute, such as the
`name` and `value` of a form, goes on the button.

```twig {"preview":true}
<twig:ReactionButton label="Like" emoji="👍" :count="3" countLabel="3 reactions" />
```

## Installation

::: installation

## Usage

```twig
<twig:ReactionButton label="Thanks" emoji="🙏" :pressed="true" name="withdraw" value="thanks" />
```

## Examples

### An emoji of the site

```twig
<twig:ReactionButton label="The gnome" image="/emojis/gnome.webp" imageAlt="A gnome waving" />
```

### Shown without being pressable

```twig
<twig:ReactionButton label="Like" emoji="👍" :count="3" countLabel="3 reactions" :interactive="false" />
```
