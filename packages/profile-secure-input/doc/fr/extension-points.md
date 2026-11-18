<!-- phil-dev source-sha256=0806a21d0ba0b761f55fcc53220d2c2fa2afb694ba9e79dc93d79d995f3c502c -->
# Points d'extension

Le profil ne contient aucun code, il n'a donc aucun point d'extension propre : une application change le comportement
dans les membres, chacun par sa configuration, ses ports et ses événements.

| Pour | Voir |
|---|---|
| Changer la façon dont les adresses sont lues, typées et stockées | La documentation de `phil/url-bundle` |
| Changer ce qu'une requête sortante peut atteindre | `Limits` et `AddressGuard` de `phil/safe-http` |
| Déclarer, indexer ou refuser des limites de débit | La configuration `phil_rate_limit` de `phil/rate-limit-bundle` |
| Ajuster les contrôles antipourriel d'un formulaire | L'option `spam_protection` de `phil/spam-protection-bundle` |
| Analyser avec un autre antivirus, ou mettre en quarantaine ailleurs | Les ports de `phil/upload-scan` et la configuration de `phil/upload-scan-bundle` |
| Se passer d'un membre | Exiger directement les autres : voir [Mise à niveau](upgrading.md) |

Les membres sont en `0.x` : leurs points d'extension ne sont stables qu'à partir de leurs versions 1.0.
