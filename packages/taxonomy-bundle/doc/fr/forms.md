<!-- phil-dev source-sha256=37cbf68d4a1f5e6bda837b87389fd5712aff8b3031268b6b09bcb287813addbe -->
# Formulaires

## `TagsType`

Des noms d'étiquettes saisis en texte séparé par des virgules, transformés en liste pour `Taxonomy::setTags()` :
`jeux, soirée`. Les noms vides et ceux qui se répètent sans égard à la casse sont omis. Non requis par défaut ; son aide
est `taxonomy.help.tags`, dans le domaine `phil_taxonomy`.

L'option `max_tags`, 20 par défaut, au moins 1 et au plus `Taxonomy::MAX_TAGS` (100), plafonne le nombre d'étiquettes distinctes ; une étiquette de plus
de 100 caractères est refusée aussi. L'un ou l'autre rend le champ invalide, avec le message `taxonomy.tags.too_many`
ou `taxonomy.tags.too_long` du domaine `validators`, en anglais et en français, dont `{{ max }}` est la limite : la
requête finit en erreur de formulaire, et non en exception de `setTags()`. Le texte est lu en temps linéaire, quelle
que soit sa longueur.

```php
$builder->add('tags', TagsType::class);
// on submit: $taxonomy->setTags($resource, $form->get('tags')->getData());
```

## `CategoryType`

Une catégorie choisie dans l'arbre, nommée dans la langue de la requête — ou celle de l'option `locale` — et mise en
retrait selon sa profondeur : `Jeux`, `— Jeux de société`, `— — Échecs`. Les choix sont les catégories elles-mêmes et
les libellés sont construits à part : deux catégories du même nom à la même profondeur sont donc toutes deux
proposées. Sa valeur est l'identifiant de la catégorie ; `multiple` permet d'en choisir plusieurs. Sa construction lit
l'arbre et ses noms en deux requêtes, quelle que soit la taille de l'arbre : les libellés prennent les noms avec
lesquels l'arbre a été lu. Quand `choices` est donné, leurs noms sont lus en une seule requête.

```php
$builder->add('categories', CategoryType::class, ['multiple' => true]);
```
