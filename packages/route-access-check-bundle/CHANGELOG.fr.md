<!-- phil-dev source-sha256=b5fd91e1211bacf0f5b3983a0bed639d6c7535e587ece94e510a05c6b8f5fe3d -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/route-access-check-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilRouteAccessCheckBundle` et la configuration `phil_route_access_check` : les environnements dans lesquels la
  vérification est enregistrée (`dev` et `test` par défaut, rien dans les autres), le fichier de référence, l'option de
  route publique, les classes d'attributs lues comme vérifications, et les noms et les chemins de routes ignorés.
- `SecurityMapPass` : les pare-feu et les entrées d'`access_control` lus de la carte des pare-feu, des configurations
  des pare-feu et de la carte d'accès que construit SecurityBundle, leurs correspondances de requêtes relues en valeurs
  simples et toute autre correspondance gardée comme son identifiant de service ; les classes des services de
  contrôleurs dont l'identifiant n'est pas leur classe.
- `CheckRouteAccessCommand`, `phil:route-access:check` : les formats `text`, `json`, `github` et `junit`, `--baseline`,
  `--generate-baseline`, `--only-problems` et `--strict` ; code de sortie 1 pour un échec non toléré, 2 pour un format
  inconnu ou un fichier de référence illisible.
- Les services de la vérification, les détecteurs étiquetés `phil_route_access_check.detector` et les formats étiquetés
  `phil_route_access_check.formatter`, les deux par autoconfiguration.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
