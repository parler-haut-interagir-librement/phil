<!-- phil-dev source-sha256=a05a60ad3a06b2e2c9d86a8a0a32d0efdcb017a250c02cdbcb9ebc5db18438cb -->
# Twig

| Nom | Nature | Donne |
|---|---|---|
| `address(locale = null)` | filtre | L'adresse sur ses lignes, jointes par des sauts de ligne — `|nl2br` les affiche — ; vide pour `null` |
| `address_lines(locale = null)` | filtre | Les lignes |
| `address_label(origin, locale = null)` | filtre | L'adresse pour une étiquette postale envoyée depuis un pays |
| `country_name(code, locale = null)` | fonction | Le nom du pays |

La langue est celle de la requête, sauf si une langue est donnée : en japonais, une adresse japonaise prend son propre
format.

## schema.org

| Fonction | Donne |
|---|---|
| `address_schema(address)` | L'adresse sous forme de `PostalAddress` de schema.org, ou `null` |

Les données s'intègrent à un document JSON-LD imprimé par le gabarit :

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` écrit `<` et `>` sous forme d'échappements : aucune valeur ne peut ainsi fermer l'élément script.
