<!-- phil-dev source-sha256=06fb6233f40a42688043b235a3828adb6c741d70fbb47db6cdc0e4c1e7c16535 -->
# Langages

Le coloriseur lit les langages que fournit `phil/highlight` (leurs noms et leurs alias sont listés dans la documentation
de cette bibliothèque) et ceux qu'ajoute l'application.

## Les langages propres à l'application

Un service qui implémente `Phil\Highlight\Language` est ajouté au coloriseur : la configuration automatique lui donne
l'étiquette `phil_highlight.language`, et un service enregistré sans configuration automatique la reçoit à la main.

```php
use Phil\Highlight\Language;

final readonly class GherkinLanguage implements Language
{
    public function name(): string { return 'gherkin'; }
    public function aliases(): array { return ['feature']; }
    public function tokenize(string $code): array { /* … */ }
}
```

Un langage prend son nom et ses alias à tout langage qui les avait : un service nommé `php` remplace le PHP de la
bibliothèque. Les langages étiquetés sont ajoutés dans l'ordre d'enregistrement de leurs services : de deux langages du
même nom, celui qui est enregistré en dernier est gardé. L'écriture d'un analyseur lexical, ou la description d'un
langage de programmation par une `Syntax`, est expliquée par le guide d'ajout d'un langage de `phil/highlight`.

## D'autres noms

`aliases` donne d'autres noms à un langage, chacun vers le nom ou un alias du langage qu'il représente. Un bloc de code
délimité comme `vue` en Markdown, ou écrit avec `vue` comme langage dans un éditeur par blocs, est alors coloré comme du
HTML :

```yaml
phil_highlight:
    aliases:
        vue: html
        jsonc: json
        feature: gherkin    # a language of the application
```

Les alias sont ajoutés après les langages étiquetés : ils peuvent donc en nommer un, et chacun prend son nom à tout
langage qui l'avait. La classe `language-…` du bloc garde le nom qu'a utilisé l'auteur.
