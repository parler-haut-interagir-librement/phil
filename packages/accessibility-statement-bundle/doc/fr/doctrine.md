<!-- phil-dev source-sha256=43ce4313e202cd629d8a083a988be398219fc680ad303fc548afd6411ce02584 -->
# Doctrine

Avec `source: doctrine`, les versions sont gardées dans la table `phil_accessibility_statement` de la connexion nommée
par `connection` :

| Colonne | Type | Contient |
|---|---|---|
| `version` | entier, clé primaire | Le numéro de la version. |
| `published_at` | `datetime_utc_immutable` | Le moment de sa publication, écrit et relu en UTC quel que soit le fuseau horaire du serveur. |
| `document` | texte | La déclaration en JSON, telle que `Statement::toArray()` l'écrit. |

La table est ajoutée au schéma que l'ORM génère pour sa connexion. `doctrine:migrations:diff` écrit la migration qui
la crée, et `doctrine:schema:validate` ne demande pas de la supprimer.

Une version est insérée une seule fois. La clé primaire refuse une deuxième version du même numéro : deux
régénérations simultanées se terminent donc par un refus (`StatementNotStored`). Rien ne modifie ni ne supprime une
ligne.

La déclaration de la configuration, s'il y en a une, est aussi une version. Elle est en vigueur tant que la table n'en
contient aucune, et elle figure parmi les autres tant que la table ne contient aucune version de son numéro. Un site
commence par écrire sa première version dans la configuration, puis laisse la commande ajouter les suivantes.

La liste des versions lit seulement les numéros et les moments de publication, jamais les documents : l'affichage
d'une page lit une seule déclaration, quel que soit le nombre de versions. Une ligne dont le document ne se lit plus
comme une déclaration est traitée comme une version absente, et son adresse répond 404. Si c'est la plus récente,
aucune version n'est en vigueur et la page répond 404, plutôt que de montrer une déclaration plus ancienne comme si
elle était actuelle. Chaque fois qu'une telle ligne est lue, un avertissement va au service `logger` :
`The accessibility statement version {version} does not read as a statement: it is served as no version.`, avec le
numéro de la version pour seul contexte, jamais ce que contient le document.
