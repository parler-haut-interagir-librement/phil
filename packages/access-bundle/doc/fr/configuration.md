<!-- phil-dev source-sha256=6a2d416ec3821c84556a677ed9a5e72811e3791f9baf6492581bf63bbaec0788 -->
# Configuration

La configuration du bundle se trouve sous `phil_access` :

```yaml
# config/packages/phil_access.yaml
phil_access:
    store: dbal
    connection: default
    table: phil_relation_tuple
    space: main
    item_kinds: [page, document]
    sharing_levels: [manage, edit, comment, view]
    default_visibility: owner
    groups:
        supporters:
            entry_points: [purchase, invitation]
            duration: P1M
        newcomers:
            entry_points: [default]
    budget:
        max_depth: 32
        max_reads: 500
        max_fan_out: 200
        max_candidates: 1000
    current_member: ~
    view_as:
        redirect: /
    easyadmin:
        actions: { detail: view, edit: edit, delete: manage }
    kit_subdirectory: ~
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `store` | `dbal` | L'endroit où les tuples sont conservés : `dbal`, ou `memory` pour les tests. |
| `connection` | `default` | La connexion Doctrine DBAL de la table des tuples. |
| `table` | `phil_relation_tuple` | Le nom de la table des tuples. |
| `space` | `main` | L'identifiant de l'espace du site, dont les visibilités nomment les membres et les administrateurs. |
| `item_kinds` | aucune | Les types d'éléments dont le bundle décide l'accès. `member`, `visitor`, `visibility`, `space` et `group` sont réservés. |
| `sharing_levels` | `[manage, edit, comment, view]` | Les niveaux, du plus large au plus étroit ; chacun inclut ceux qui le suivent, et le dernier est ce qu'ouvre une visibilité. De 1 à 16 noms de 1 à 32 lettres minuscules, chiffres ou `_` ; `owner`, `space` et `visibility` sont réservés. |
| `default_visibility` | `owner` | La visibilité d'un élément enregistré sans visibilité : `everyone`, `members`, `admins` ou `owner`. |
| `groups` | aucun | Les groupes d'accès, par nom : leurs `entry_points` — `purchase`, `invitation`, `bulk`, `automation`, `default` — et une `duration`, une durée ISO 8601 après laquelle une adhésion se termine d'elle-même. |
| `budget` | comme illustré | Les limites d'une vérification ou d'une liste : profondeur, lectures du magasin, ensembles derrière une relation, candidats d'une liste ; de 1 à 100 000 chacune. |
| `current_member` | aucun | Le service qui nomme le membre à l'origine de la requête ; l'identifiant d'utilisateur du jeton de sécurité avec SecurityBundle, personne sans lui. |
| `view_as.redirect` | `/` | Le chemin du site auquel reviennent les formulaires de « voir comme ce membre » ; un chemin, jamais une adresse. |
| `easyadmin.actions` | comme illustré | Chaque action d'EasyAdmin et le niveau qu'elle exige. Sans cette option, les niveaux par défaut associent `detail`, `edit` et `delete` ; avec d'autres niveaux, `detail` correspond au plus étroit. |
| `kit_subdirectory` | aucun | L'endroit où se trouvent les recettes du kit, sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand l'option est nulle. |
