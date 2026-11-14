<!-- phil-dev source-sha256=0d5ac150b2c8efd8ffed6c18690954f550d9b6557a273a1543c5ba67aaaf0127 -->
# Points d'extension

La bibliothèque ne déclare aucun service. Ce qu'une application peut implémenter ou remplacer, c'est le dessin et la
conservation des cartes.

## `CardRenderer`

`Phil\SocialCards\Contract\CardRenderer` dessine une carte. `GdCardRenderer` et `ImagickCardRenderer` sont ceux qui
sont fournis ; un moteur de rendu d'une autre bibliothèque l'implémente.

| Méthode | Contrat |
|---|---|
| `version(): string` | Ce dont l'image dépend en plus du gabarit et du contenu ; elle entre dans chaque empreinte |
| `render(CardTemplate $template, CardContent $content): string` | Un PNG de 1200×630, les mêmes octets pour les mêmes arguments et la même version |

## `TextMeasure`

`Phil\SocialCards\Contract\TextMeasure` mesure une ligne telle que la dessine le moteur de rendu, pour `TextFitter`.

| Méthode | Contrat |
|---|---|
| `width(string $text, CardFont $font, int $size): int` | La largeur en pixels à une taille en pixels ; 0 pour le texte vide |

## `CardStore`

`Phil\SocialCards\Contract\CardStore` garde les cartes dessinées par empreinte. `DirectoryCardStore` et
`InMemoryCardStore` sont fournis ; un stockage sur un stockage d'objets l'implémente.

| Méthode | Contrat |
|---|---|
| `get(string $fingerprint): ?string` | Le PNG, ou null |
| `put(string $fingerprint, string $png): void` | Garde le PNG ; un lecteur ne voit jamais une demi-carte |

Un stockage refuse ce qui n'est pas une empreinte, avec `Fingerprint::check()`.
