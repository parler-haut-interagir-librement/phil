<!-- phil-dev source-sha256=7364824b5ef045b35e8b3807cf3c15e56c6810e5655e6cabc4ab57e53b1b9993 -->
# Exceptions

Chaque exception que lève la bibliothèque implémente `Phil\CommerceProductTemplate\Exception\ProductTemplateException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidDefinition` | `InvalidArgumentException` | Une partie d'un gabarit ou d'un visuel est refusée à la construction, ou relue à partir d'un tableau qu'aucun code de la bibliothèque n'a écrit |
| `PlanRefused` | `DomainException` | Un visuel ne peut pas être planifié sur un gabarit : voir [Planification](planning.md) |

Les messages nomment le code, la valeur ou le chemin refusé, à l'intention de la personne qui tient les gabarits à jour.
