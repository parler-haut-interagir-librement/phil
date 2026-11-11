<!-- phil-dev source-sha256=e19932f1a7ca4a60eb6df8a6c5ba4bdfec991ec0880f7bf38a09ada7bfdb5984 -->
# Twig

| Filtre | Donne |
|---|---|
| `phone(format = 'international')` | Le numéro en `international`, `national`, `e164` ou `rfc3966` ; un texte illisible tel quel, vide pour `null` |
| `phone_link` | L'adresse `tel:`, ou `null` pour un texte illisible |

Un texte est lu dans le `default_region` du bundle.

```twig
{% set link = shop.phone|phone_link %}
{% if link %}<a href="{{ link }}">{{ shop.phone|phone('national') }}</a>{% endif %}
```
