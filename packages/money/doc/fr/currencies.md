<!-- phil-dev source-sha256=f6ddebfd8189f4392263d85027f474a1564543aa2dd392c68e7920ca5b41bb94 -->
# Devises

`Phil\Money\Currency` est un code alphabétique ISO 4217 que connaissent les données Unicode CLDR de `symfony/intl`.

```php
Currency::of('CAD');   // ok
Currency::of('XYZ');   // UnknownCurrency: the shape of a code, but no currency
Currency::of('cad');   // UnknownCurrency: codes are case-sensitive
```

**Connu, et pas seulement bien formé.** Un code qui ne désigne rien n'a ni précision, ni nom, ni symbole ; le refuser à
l'entrée garde chaque montant porteur de sens. Un code en minuscules est refusé plutôt que corrigé : c'est le signe
d'une entrée qui n'a jamais été validée. Les devises qu'une application *accepte* sont une décision plus étroite, prise
par sa configuration (`phil_money.currencies` avec le bundle).

| Membre | Contrat |
|---|---|
| `static of(string $code): self` | La devise, ou `UnknownCurrency` ; la même instance pour le même code |
| `public string $code` | Le code de trois lettres |
| `fractionDigits(): int` | Le nombre de décimales d'un montant : 2 pour `CAD`, 0 pour `JPY`, 3 pour `BHD`, 4 pour `CLF` |
| `name(?string $locale = null): string` | `dollar canadien` en `fr`, `Canadian Dollar` en `en` ; la locale par défaut quand aucune n'est donnée |
| `symbol(?string $locale = null): string` | `$` en `fr_CA`, `CA$` en `en` |
| `equals(self $other): bool` | Même code |
| `__toString(): string` | Le code |

**Une instance par code.** Le premier `of()` d'un code le cherche dans les données CLDR, ce qui coûte plusieurs
microsecondes ; chaque appel ultérieur retourne la même instance à partir d'une table propre au processus. Une entité
qui garde un code et reconstruit sa devise à chaque lecture paie un accès à un tableau, pas une recherche. Seuls les
codes connus entrent dans la table, qui ne contient donc jamais plus que les quelques centaines de codes que connaît le
CLDR. Comparez tout de même les devises avec `equals()` : l'instance partagée est une économie, pas un contrat qui
permet de comparer par identité.

Le nombre de décimales est celui du CLDR, c'est-à-dire ce qu'une personne s'attend à lire et à taper ; un processeur de
paiement peut compter certaines devises autrement (la couronne islandaise, par exemple), et son adaptateur fait la
conversion.
