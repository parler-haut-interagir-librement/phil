<!-- phil-dev source-sha256=cbbea16bb5770b3286938a26f23cd5a2d5999ec1fba0eecb4636b3649a1ccaff -->
# Mode worker et profileur

## Ce qui vit où

| Quoi | Où | Durée de vie |
|---|---|---|
| Les politiques nommées, leurs chaînes d'en-tête | `PolicyRegistry`, construit à partir de la configuration | le processus ; jamais changées |
| Les surcharges, nonces et empreintes d'une requête, les en-têtes écrits | `RequestState`, dans les attributs de la requête principale | la requête |
| Les compteurs de la limitation de débit, la fenêtre de dédoublonnage | les pools PSR-6 de la configuration | partagés par chaque worker |
| Les violations | la table `phil_csp_report` | jusqu'à la purge |

Aucun service ne garde quoi que ce soit entre deux requêtes : un worker de FrankenPHP ou de RoadRunner sert chaque
requête avec ses propres nonces et surcharges, et n'a besoin d'aucun `kernel.reset` pour le bundle. Le collecteur du
profileur se vide lui-même à `reset()`, comme Symfony l'attend.

## Sous-requêtes

Seule la requête principale reçoit des en-têtes. Un fragment rendu dans une sous-requête (`render(controller())`, ESI
rendu en ligne) partage les nonces et les surcharges de sa page, puisque `csp_nonce()` et `ContentSecurityPolicyContext`
écrivent dans la requête principale.

## Le profileur

Avec WebProfilerBundle, le panneau *Content Security Policy* montre la politique nommée appliquée, les en-têtes écrits
(une directive par ligne), le nombre de surcharges et les sortes de nonce que les gabarits ont demandées. Les chemins du
profileur et de sa barre d'outils sont dans `exclude_paths` : la barre d'outils apporte ses propres nonces.
