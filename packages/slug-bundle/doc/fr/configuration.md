<!-- phil-dev source-sha256=ecbdbe603880c9443b80e32a607037f80188ad806b601b44cc64a150a1eb7b6a -->
# Configuration

```yaml
# config/packages/phil_slug.yaml
phil_slug:
    default: default            # the slugger used when none is named
    sluggers:
        default: ~
        titles:
            locale: fr          # the transliteration of a locale; null for the generic one
            max_length: 80      # the longest slug, cut at the last hyphen that fits (1 to 255)
        directory:
            leading_words: [le, la, les, l, the]
    history:
        enabled: false          # the history of former slugs, see history.md
        connection: default     # its DBAL connection
        clock: clock            # its PSR-20 clock
```

| Clé | Valeur par défaut | Règle |
|---|---|---|
| `default` | `default` | Le nom de l'un des `sluggers` ; non vide |
| `sluggers` | un générateur nommé d'après `default` | Par nom : une lettre minuscule, puis des lettres minuscules, des chiffres ou `_` (`-` est lu comme `_`) |
| `sluggers.<name>.locale` | `null` | Toute locale dans laquelle `symfony/string` translittère |
| `sluggers.<name>.max_length` | `255` | De 1 à 255 |
| `history.enabled` | `false` | `true` enregistre l'historique des anciens slugs ; demande `doctrine/dbal` |
| `history.connection` | `default` | Un nom de connexion DBAL ; non vide |
| `history.clock` | `clock` | Un identifiant de service d'horloge PSR-20 ; non vide |
| `sluggers.<name>.leading_words` | `[]` | Les mots retirés une fois du début d'un slug quand d'autres suivent, écrits comme des mots de slug : chacun une seule suite de lettres ASCII minuscules et de chiffres |

- Un `default` qui ne nomme aucun des `sluggers` donnés est refusé, avec les noms qui existent.
- Un mot de tête qui n'est pas un seul mot de slug — `Le`, `l'`, `de-la` — est refusé à la construction du
  conteneur, selon la règle de `LeadingWordsRemoved::checkWord()`, plutôt qu'à la première requête qui construit le
  générateur.
- Sans `sluggers`, le bundle en enregistre un, nommé d'après `default`, avec chaque option à sa valeur par défaut.
- Un générateur avec `leading_words` est un `LeadingWordsRemoved` autour d'un `TransliteratingSlugger` ; un
  générateur sans eux est le `TransliteratingSlugger` seul.
- Avec `history.enabled` et sans `doctrine/dbal`, la construction du conteneur échoue et dit quel paquet demander.
  Voyez [Historique des anciens slugs](history.md).
- **Aucun générateur ne retire de mots si la configuration ne le dit pas** : les articles d'une langue, les préfixes
  propres à un site, sont le choix de chaque application.
