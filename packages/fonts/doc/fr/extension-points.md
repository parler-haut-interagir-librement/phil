<!-- phil-dev source-sha256=ce0f8d84d74ab190a5ffb72340e3870fb26961b0148d465faaace02fac810032 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port. Ce que choisit une application passe par des
arguments.

| Point | Comment |
|---|---|
| Le décodeur Brotli de WOFF2 | Le dernier argument de `FontFile::fromBytes()`, `FontFile::fromFile()` et `LicenceRegister::scan()` : `Brotli::decompressor()` pour `ext-brotli`, `Brotli::decompressor('my_decode')` pour une autre fonction, ou n'importe quelle fermeture qui reçoit les octets compressés et la longueur attendue et retourne les octets ou `false` |
| La famille que déclare une règle | `FontFaceRule::withFamily()`, pour une face dont la famille est renommée sur le site |
| Les sources d'une règle | `withUrl()` une fois par fichier, le plus petit format d'abord ; `withoutLocal()` pour toujours télécharger |
| L'endroit où vit un registre | `LicenceRegister::toJson()` et `fromJson()` : l'application stocke le JSON où elle le veut |

Un décodeur qui retourne plus ou moins d'octets que la longueur qui lui est donnée est refusé avec `InvalidFont` : la
bibliothèque ne confie jamais au décodeur les limites de ce qu'elle lit.
