# Forms

## `TagsType`

Tag names typed as text separated by commas, to a list for `Taxonomy::setTags()`: `jeux, soirée`. The empty ones and
the ones repeated without regard to case are left out. Not required by default; its help is
`taxonomy.help.tags`, in the `phil_taxonomy` domain.

The `max_tags` option, 20 by default, at least 1 and at most `Taxonomy::MAX_TAGS` (100), caps the number of distinct tags; a tag longer than 100
characters is refused too. Either makes the field invalid, with the message `taxonomy.tags.too_many` or
`taxonomy.tags.too_long` of the `validators` domain, in English and French, whose `{{ max }}` is the limit: the
request ends in a form error, not in an exception from `setTags()`. The text is read in linear time, whatever its
length.

```php
$builder->add('tags', TagsType::class);
// on submit: $taxonomy->setTags($resource, $form->get('tags')->getData());
```

## `CategoryType`

A category picked from the tree, named in the request's language — or the `locale` option's — and indented by depth:
`Jeux`, `— Jeux de société`, `— — Échecs`. The choices are the categories themselves and the labels are built apart,
so two categories of the same name at the same depth are both offered. Its value is the category's identifier;
`multiple` picks several. Building it reads the tree and its names in two queries, whatever the size of the tree:
the labels take the names the tree was read with. With `choices` given, their names are read in one query.

```php
$builder->add('categories', CategoryType::class, ['multiple' => true]);
```
