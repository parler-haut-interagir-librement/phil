<!-- phil-dev source-sha256=654f6d0db63df4f98bc4824fea088c79471f515c0f96886301bd8bbc1b44bebd -->
# Avertissements

## Un avertissement

Un `ContentWarning` est un avertissement d'un contenu : la clé de son thème, la sévérité du thème à cet endroit
lorsqu'elle est connue, et une note facultative — `new ContentWarning('gore', Severity::Severe, 'The final battle in
chapter 12')`. La note est épurée de ses espaces de début et de fin et compte 500 caractères au plus ; une note vide
équivaut à aucune note.

`Severity` va de `Mild` à `Moderate` à `Severe` : une mention en passant, une scène montrée, une scène sur laquelle on
s'attarde. `isAtLeast()` compare deux sévérités. Un avertissement sans sévérité est pris à son niveau le plus sévère
lors de la comparaison avec les préférences.

Un avertissement désigne son thème par sa clé et n'est pas vérifié par rapport à un catalogue : un contenu conserve ses
avertissements lorsqu'un libellé change, et un thème qu'une application retire reste sur le contenu qui le nommait. Le
code d'affichage lit le libellé du thème dans le catalogue et se rabat sur la clé pour un thème que le catalogue n'a
plus.

## Les avertissements d'un contenu

`ContentWarnings` contient un avertissement par thème, dans l'ordre fourni, 100 au plus.

| Méthode | Donne |
|---|---|
| `none()`, `of(...$warnings)`, `forThemes(...$keys)` | Une liste ; un thème fourni deux fois est refusé |
| `get($theme)`, `has($theme)`, `themes()` | Un avertissement, s'il y en a un, les clés de thèmes |
| `with($warning)`, `without($theme)` | Une nouvelle liste avec un avertissement ajouté, qui remplace sur place celui de son thème, ou retiré |
| `isEmpty()`, `count()`, itération | Les avertissements, dans l'ordre |

## Stockage

`toArray()` écrit une liste pour une seule colonne JSON, en laissant de côté ce qui n'est pas défini :

```php
[['theme' => 'gore', 'severity' => 'severe', 'note' => 'The final battle'], ['theme' => 'spiders']]
```

`ContentWarnings::fromArray()` la relit et lève `UnreadableWarnings` pour tout ce que `toArray()` n'écrit pas : une
entrée qui n'est pas un tableau, une clé inconnue, un thème qui n'est pas une clé valide, une sévérité inconnue, un
thème présent deux fois, plus de 100 avertissements.
