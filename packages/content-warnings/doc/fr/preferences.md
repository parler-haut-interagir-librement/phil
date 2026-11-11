<!-- phil-dev source-sha256=4f7bc7202826b590b852bfa434b7a2a2b66a591c7791ab6259bdd3d374263324 -->
# Préférences et décisions

## Affichages

`Display` indique ce qui arrive à un contenu qui porte un avertissement : l'afficher (`Show`) avec son avertissement
visible, le replier (`Fold`) derrière son avertissement jusqu'à ce que le lecteur le révèle, ou le masquer (`Hide`). Les
cas vont du plus ouvert au plus strict ; `isStricterThan()` en compare deux, et `Display::strictest(...$displays)` donne
le plus strict, `Show` s'il n'y en a aucun. Le voile d'un outil de sécurité replie, sa ligne masque.

## Les préférences d'un lecteur

`WarningPreferences` contient les choix d'un lecteur : une `Preference` par thème, une `Preference` par catégorie et
l'affichage par défaut de tout autre avertissement — `Fold` si rien n'est fourni.

```php
$preferences = WarningPreferences::defaults(Display::Show)
    ->withCategory('phobias', Display::Fold)
    ->withTheme('gore', Display::Hide, Severity::Moderate)
    ->withTheme('spiders', Display::Hide);
```

Une `Preference` est un affichage, à partir d'une sévérité lorsqu'il y en a une : sous cette sévérité, elle ne
s'applique pas. Un avertissement de sévérité inconnue est pris à son niveau le plus sévère : la préférence s'y applique
donc.

| Méthode | Donne |
|---|---|
| `defaults($display)`, `withDefault($display)` | L'affichage par défaut |
| `withTheme()`, `withoutTheme()`, `withCategory()`, `withoutCategory()` | De nouvelles préférences, avec un élément ajouté, remplacé ou retiré |
| `theme($key)`, `category($key)`, `themes()`, `categories()` | Les préférences conservées |
| `themesWith($display)` | Les clés des thèmes qui ont cet affichage, quelle que soit la sévérité |
| `displayFor($warning, $catalogue)` | L'affichage d'un avertissement |

Pour un avertissement, la préférence de son thème l'emporte sur celle de la catégorie de son thème, lue dans le
catalogue, qui l'emporte sur la valeur par défaut ; une préférence qui ne s'applique pas à la sévérité de
l'avertissement cède la place à la suivante. Un thème que le catalogue n'a pas répond seulement à sa propre préférence
et à la valeur par défaut. Les préférences contiennent 2 200 entrées au plus.

`toArray()` les écrit pour le stockage — `['default' => 'fold', 'themes' => ['gore' => ['display' => 'hide', 'at_least'
=> 'moderate']], 'categories' => []]` — et `WarningPreferences::fromArray()` les relit, en levant `UnreadableWarnings`
pour toute autre chose.

## Décisions

`WarningMatcher` est construit avec le catalogue et décide pour un contenu et un lecteur :

```php
$decision = new WarningMatcher($catalogue)->decide($warnings, $preferences);
```

La `Decision` contient le `display` — l'affichage le plus strict des avertissements, `Show` sans avertissement —, tous
les `warnings`, et `because`, les avertissements qui ont donné cet affichage. `isShown()`, `isFolded()`, `isHidden()` et
`hasWarnings()` la lisent. Une décision prend un temps linéaire selon le nombre d'avertissements, environ une
microseconde chacun.
