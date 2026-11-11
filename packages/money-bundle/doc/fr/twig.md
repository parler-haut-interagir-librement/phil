<!-- phil-dev source-sha256=925e5a96661da875849b576b7e0024b60db091892761fca3ee9428f8cca6dd9b -->
# Twig

## `format_money`

L'équivalent pour `Money` du `format_currency` de Twig :

```twig
{{ product.price|format_money }}          {# 1 234,50 $ in fr_CA, CA$1,234.50 in en #}
{{ product.price|format_money('en_CA') }} {# $1,234.50 #}
{{ null|format_money }}                   {# nothing #}
```

| Argument | Valeur par défaut | Sens |
|---|---|---|
| `locale` | celle de la requête | La locale du symbole, des séparateurs et du motif |

Le filtre met en forme avec le motif monétaire d'ICU pour la locale, à la précision propre à la devise. Ses chiffres
parviennent à ICU sous forme de nombre à virgule flottante, exact jusqu'à 2^53 unités mineures, soit environ 90 billions
de dollars. Un formateur est construit par locale et conservé, pour 64 locales au plus, de sorte que les locales tirées
des requêtes ne peuvent pas faire grossir la mémoire d'un *worker* qui vit d'une requête à l'autre ; les séparateurs que
lit `LocalizedDecimal` pour les formulaires sont conservés de la même façon.

## Avec `format_currency`

Une application qui utilise déjà `twig/intl-extra` peut mettre en forme un `Money` avec son filtre, à partir du
décimal :

```twig
{{ product.price.toDecimal|format_currency(product.price.currency.code) }}
```

`format_money` épargne les deux lectures de propriété, et fonctionne sans `twig/intl-extra`.

## schema.org

| Fonction | Donne |
|---|---|
| `money_schema(money)`, `price_schema(money)` | Un `MonetaryAmount` de schema.org ; le `price` et le `priceCurrency` d'un `Offer` ; ou `null` |

Les données se composent en un document JSON-LD qu'affiche le gabarit :

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` écrit `<` et `>` sous forme d'échappements, de sorte qu'aucune valeur ne peut fermer l'élément script.
