<!-- phil-dev source-sha256=60c071fa1e52bbe4a21737e98c2af78f4372b58dbf7603bd1f6659f40319dd0c -->
# Validation et types

| Méthode | Donne |
|---|---|
| `isValid()` | Si le numéro existe dans le plan de numérotation de son pays |
| `region()` | Le pays du numéro, `001` pour les numéros d'aucun pays, `null` quand il ne figure dans aucun plan |
| `type()` | Ce qu'est le numéro, `PhoneNumberType::Unknown` quand il ne figure dans aucun plan |

Les pays qui partagent un indicatif — les 25 du `+1`, le Royaume-Uni et ses dépendances de la Couronne — se
distinguent par leurs premiers chiffres et leurs plans : `+44 7624…` est l'île de Man, `+39 06 698…` le Vatican.

## Les types

| `PhoneNumberType` | Signifie |
|---|---|
| `FixedLine`, `Mobile` | Un fixe, un mobile |
| `FixedLineOrMobile` | Un numéro d'un plan où on ne peut pas les distinguer, comme en Amérique du Nord |
| `TollFree`, `PremiumRate`, `SharedCost` | Gratuit pour l'appelant, surtaxé, coût partagé |
| `Voip`, `PersonalNumber`, `Pager`, `Uan`, `Voicemail` | Téléphonie par Internet, un numéro qui suit une personne, un téléavertisseur, un numéro d'accès universel, une boîte vocale |
| `Unknown` | Dans aucun plan |

Un champ pour les mobiles accepte `Mobile` et `FixedLineOrMobile` ; sinon, les numéros nord-américains sont refusés.
