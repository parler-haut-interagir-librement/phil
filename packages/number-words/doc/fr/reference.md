<!-- phil-dev source-sha256=0fe2e5bed58a464374c706438708385e2ba03833777c301b7f4ca9451c634715 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `NumberWords` | Épelle des cardinaux, des ordinaux et des montants dans une locale, par ICU et les règles de la langue. |
| `SpellingOptions` | L'orthographe du français, les traits d'union conditionnels et les formes détaillées. |
| `FrenchSpelling` | `Traditional` ou `Rectified1990`. |
| `Gender` | `Masculine`, `Feminine` ou `Neuter` : le genre avec lequel un nombre s'accorde. |
| `Kind` | `Counting`, `Cardinal` ou `Ordinal` : ce qu'est un nombre, pour les corrections d'une langue. |
| `CurrencyName` | Les noms d'une devise dans une langue : unité et sous-unité, singulier et pluriel, leurs genres, les décimales. |
| `Contract\LanguageRules` | Ce qu'une langue ajoute à ICU : corrections, noms de devises et grammaire des montants. |
| `Language\AbstractLanguageRules` | La grammaire commune des montants, pour qu'une langue fixe ses mots. |
| `Language\French`, `English`, `Spanish`, `German`, `Italian`, `Portuguese` | Les règles des six langues. |
| `Exception\NumberWordsException` | Implémentée par chaque exception de la bibliothèque. |
| `Exception\IntlUnavailable` | `ext-intl` n'est pas chargée. |
| `Exception\UnsupportedLocale` | ICU n'épelle pas la langue de la locale, les règles sont pour une autre, ou la langue n'a pas de règles pour les montants. |
| `Exception\MissingRule` | Un jeu de règles, une devise ou une sous-unité que la langue n'a pas. |
| `Exception\NumberOutOfRange` | Un nombre trop grand pour les lettres, un ordinal négatif, une devise de plus de 4 décimales. |
| `Exception\InvalidAmount` | Un montant qui n'est pas un nombre décimal, ou plus précis que sa devise. |
