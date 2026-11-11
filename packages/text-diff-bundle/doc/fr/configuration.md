<!-- phil-dev source-sha256=8876df4cb042c7f4c0b31621a3983e55125cec182a3beb70c85cdb49c949212e -->
# Configuration

```yaml
phil_text_diff:
    max_edits: 1000
    tokenizer: words
    html_sanitizer: html_sanitizer.sanitizer.app
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `max_edits` | `1000` | Le nombre maximal de modifications qu'une comparaison recherche, à partir de zéro ; au-delà, le reste des deux textes est affiché comme remplacé en entier |
| `tokenizer` | `words` | Ce par quoi `text_diff` compare quand le gabarit ne nomme rien : `words`, `lines`, `paragraphs` ou `html` |
| `html_sanitizer` | aucune | L'identifiant de service d'un `Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface` : le HTML non marqué donné à une comparaison par `html` passe par lui ; sans lui, ce HTML est échappé |

`max_edits` limite le temps et la mémoire sur des textes réécrits d'un bout à l'autre : la recherche croît avec la
longueur des textes multipliée par le nombre de modifications, et sa mémoire avec le carré de ce nombre. Mille
maintiennent une comparaison sous quelques mégaoctets ; la documentation de `phil/text-diff` en dit plus sous
*Comparing texts*.

`html_sanitizer` nomme l'un des assainisseurs que déclare FrameworkBundle :

```yaml
framework:
    html_sanitizer:
        sanitizers:
            app.revisions:
                allow_safe_elements: true

phil_text_diff:
    html_sanitizer: html_sanitizer.sanitizer.app.revisions
```

Une valeur de configuration hors de ces cas — une borne négative, un découpeur inconnu — fait échouer la construction
du conteneur.
