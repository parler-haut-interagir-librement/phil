# Twig

The filter is registered when Twig is installed. It keeps the `phil_` prefix so that it does not clash with the `slug`
filter of `twig/string-extra`.

| Filter | Arguments | Gives |
|---|---|---|
| `phil_slug` | `slugger` (optional): the name of a slugger | The slug of the text, by that slugger or the default one |

```twig
{{ 'Jeux de rôle'|phil_slug }}                       {# jeux-de-role #}
{{ "L'Ordre du Corbeau"|phil_slug('directory') }}    {# ordre-du-corbeau, with leading_words [l, …] #}
```

- A text with nothing to make a slug of (`'?!'`) and `null` give an **empty string**: a class name or a sort key is
  better empty than an error page.
- An unknown slugger name is an error (`UnknownSlugger`): it is a mistake in the template, not in the data.
