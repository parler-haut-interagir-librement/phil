<!-- phil-dev source-sha256=e275f95432e2fba64d7b1d9d96961d91635b8287e96f8550358f1e20c96e8277 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\OpenBadges\Exception\OpenBadgesException` : attrapez-la pour les
attraper toutes.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidBadge` | `InvalidArgumentException` | Une valeur donnée pour construire un titre est incohérente : un émetteur, une réalisation, un destinataire, une clé, un identifiant, une période, un appui, une entrée ou une liste d'état, un relevé d'apprentissage. |
| `InvalidCredential` | `RuntimeException` | Un titre ne se vérifie pas : mal formé, signé avec un autre algorithme ou une autre clé, modifié, pas encore valide, expiré, utilisant du JSON-LD hors du sous-ensemble, ou trop coûteux à canonicaliser ; ou son état ne peut pas être lu. |
| `InvalidImage` | `RuntimeException` | Une image dans laquelle un titre est intégré ou lu est mal formée, trop grande, n'est ni un PNG ni un SVG, contient deux titres, ou contient autre chose qu'un titre. |

Chacune porte sa raison comme message, en anglais, pour les journaux. Une page de vérification dit à la personne que
le titre ne se vérifie pas, et peut afficher la raison.
