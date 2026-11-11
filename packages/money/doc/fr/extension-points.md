<!-- phil-dev source-sha256=e3bca74281f718873c488825eb98556ca5e5270244850d4ad4cd4bd8527db4d6 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port, et ses classes sont finales. Ce que choisit une
application passe par des arguments, et ce qu'elle ajoute s'écrit à côté de la bibliothèque.

| Point | Comment |
|---|---|
| Les devises acceptées | `Currency::of()` accepte chaque code ISO 4217 des données CLDR ; une application qui vend dans moins de devises garde sa propre liste et y vérifie un code avant d'appeler la bibliothèque |
| L'arrondi d'une quote-part | `allocate()` et `split()` décident où vont les unités restantes ; une autre règle s'écrit à partir de la propriété `minorUnits` et de `Money::of()`, jamais avec des nombres à virgule flottante |
| L'affichage d'un montant | `Money::toDecimal()` donne les chiffres ; l'application les met en forme dans sa locale, ou utilise `SchemaOrg::amount()` et `SchemaOrg::price()` pour les données structurées |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Phil\Money\Exception\MoneyException` : un seul `catch` couvre `UnknownCurrency`, `InvalidAmount` et `CurrencyMismatch` |

Une conversion entre devises ne relève pas de la bibliothèque : les taux appartiennent à l'application, qui multiplie
les unités mineures et crée un nouveau `Money` dans la devise cible.
