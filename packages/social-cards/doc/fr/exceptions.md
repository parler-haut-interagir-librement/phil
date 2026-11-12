<!-- phil-dev source-sha256=ad02177df92c52dda13b082a5056055a1bea097cdd0422ecf1b45a19b3c098b7 -->
# Exceptions

Chaque exception implémente `Phil\SocialCards\Exception\SocialCardsException`.

| Exception | Lancée quand |
|---|---|
| `InvalidShareMetadata` | Une page partagée n'a pas de titre, a une adresse qui n'est pas HTTP ou HTTPS absolue, une locale, un compte, une taille d'image ou un type de média qui n'en est pas un, une clé inconnue ou une valeur du mauvais type dans `fromArray()` ; la locale d'une carte n'en est pas une |
| `InvalidCardTemplate` | Un gabarit ou une carte a une couleur, une taille ou une borne hors limites, une clé inconnue, une police illisible ou qui n'est pas TrueType ni OpenType, une image qui n'est pas un PNG, JPEG, GIF ou WebP dans les bornes, aucun titre ; un stockage reçoit ce qui n'est pas une empreinte |
| `CardRenderingFailed` | GD ne peut pas mesurer ni dessiner du texte dans une police, lire une image ou écrire le PNG ; un stockage ne peut pas écrire une carte |

Le lecteur des pages d'autres sites, `TagReader`, `LinkPreview` et `TagCheck` ne lancent rien.
