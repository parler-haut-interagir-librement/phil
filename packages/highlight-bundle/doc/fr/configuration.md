<!-- phil-dev source-sha256=56dd03e27eed0050a07a46c9d032496bb3a156f72e2e70d0edc92b729c1e3971 -->
# Configuration

```yaml
# config/packages/phil_highlight.yaml
phil_highlight:
    aliases:              # more names for a language, each to the name it stands for
        vue: html
        jsonc: json
    ujo:
        enabled: true     # colour the code blocks of UJO documents, when phil/ujo-bundle is installed
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `aliases` | `{}` | D'autres noms sous lesquels un langage est demandé, chacun vers le nom ou l'alias d'un langage que lit le coloriseur : un langage fourni ou l'un de ceux de l'application. Voir [Langages](languages.md) |
| `ujo.enabled` | `true` | `false`, ou `ujo: false`, laisse échappés les blocs de code des documents UJO, pour que le navigateur les colore |

Un nom s'écrit comme le coloriseur l'accepte : en minuscules, avec des lettres, des chiffres et `_+#.-`, jusqu'à 32
caractères. Tout autre alias ou nom est refusé à la construction du conteneur. Un nom qu'aucun langage ne porte est
détecté à la première construction du coloriseur, par une [`UnknownLanguage`](exceptions.md).

## Services

| Service | Construit comme |
|---|---|
| `Phil\Highlight\Highlighter` | `Highlighter::default()`, puis `with()` pour chaque langage étiqueté et chaque groupe d'alias ; public |
| `phil_highlight.code_highlighter` | `Closure::fromCallable([Highlighter, 'highlight'])`, quand `ujo.enabled` est activé et que le moteur de rendu de `phil/ujo-bundle` est un service |
| `phil_highlight.ujo_renderer` | Décore `Phil\Ujo\Html\HtmlRenderer` avec `withCodeHighlighter()` et cette fermeture |
| `Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension` | Le filtre, quand Twig est installé |

Injectez le coloriseur là où du code est coloré hors des gabarits :

```php
public function __construct(private Highlighter $highlighter)
{
}
```

Chaque service qui implémente `Phil\Highlight\Language` reçoit l'étiquette `phil_highlight.language` quand il est
configuré automatiquement.
