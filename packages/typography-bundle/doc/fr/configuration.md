<!-- phil-dev source-sha256=a81f3a910c62c8cbca381656e926b3a2b40e27951e300d217af551eb91aeb9c4 -->
# Configuration

```yaml
# config/packages/phil_typography.yaml
phil_typography:
    default_locale: ~            # the kernel's default locale when null
    hyphenation: true            # whether presets insert soft hyphens
    experimental_hyphenation: [] # hy, mk: experimental patterns, off by default
    protected_tags: [pre, code, kbd, script, style, textarea]
    locales:
        fr_CA: []                # a service with the preset of fr_CA
        en: [ellipsis, smart_quotes, curly_quote, dash]
    ujo:
        enabled: true            # fix the HTML of UJO documents, when phil/ujo-bundle is installed
        locale: ~                # the locale of the request when null
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `default_locale` | `null` | La locale du service `Typographer`, et celle des filtres et de la passe UJO quand le `LocaleSwitcher` de Symfony n'est pas enregistré ; `%kernel.default_locale%` si elle est nulle |
| `hyphenation` | `true` | `false` retire `hyphen`, et `word_break` pour le thaï, de chaque préréglage ; une locale configurée avec eux les garde |
| `experimental_hyphenation` | `[]` | Les motifs de césure expérimentaux servis, parmi `hy` (arménien) et `mk` (macédonien) ; toute autre valeur est refusée à la construction du conteneur. Sans eux, ces langues n'ont pas de césure |
| `protected_tags` | `pre`, `code`, `kbd`, `script`, `style`, `textarea` | Les éléments dont le texte n'est jamais modifié |
| `locales` | `{}` | Un service de typographe pour chaque locale ; une liste de noms de correcteurs, dans leur ordre d'exécution, remplace son préréglage et sert aussi les locales plus précises de la même langue ; une liste vide garde le préréglage. Un nom inconnu est refusé à la construction du conteneur |
| `ujo.enabled` | `true` | `false` laisse le HTML des documents UJO tel que `phil/ujo` le produit |
| `ujo.locale` | `null` | La locale de cette passe ; la locale courante de la requête si elle est nulle, comme pour les filtres |

Les noms de correcteurs sont ceux de `Preset::FIXERS` : `unicode_normalization`, `ellipsis`, `dimension`, `dash`,
`smart_quotes`, `curly_quote`, `no_space_before_comma`, `space_before_punctuation`, `trademark`, `unit`, `hyphen`,
`word_break`.

## Services

| Service | Construit comme |
|---|---|
| `Phil\Typography\Hyphenation\Dictionary` | Les motifs fournis, lus une fois et partagés, avec les motifs expérimentaux de `experimental_hyphenation` |
| `Phil\Typography\Typographers` | Selon les options ci-dessus ; public |
| `Phil\Typography\Typographer` | `Typographers::for()`, la locale par défaut ; public |
| `phil_typography.typographer.<locale>` | `Typographers::for('<locale>')`, pour chaque clé de `locales` ; public |
| `phil_typography.ujo_filter` | `TypographyHtmlFilter`, quand `ujo.enabled` est vrai et que `phil/ujo` est installé ; avec `translation.locale_switcher` s'il existe |
| `phil_typography.ujo_renderer` | Décore `Phil\Ujo\Html\HtmlRenderer` avec ce filtre |

Chaque locale configurée est aussi injectée dans un argument qui porte son nom, la locale en camel case :

```php
public function __construct(
    private Typographer $typographer,          // the default locale
    private Typographer $enGBTypographer,      // phil_typography.typographer.en_GB
) {
}
```

`Typographers::for()` accepte n'importe quelle locale, configurée ou non : une locale sans configuration reçoit son
préréglage.
