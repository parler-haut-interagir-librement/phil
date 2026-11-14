<!-- phil-dev source-sha256=a7cb4c6730a1567487bd5ed59f4d4edb6e566b7a1d3c4a89da0942d70846735b -->
# La connexion de chaque locataire

## Une connexion, réécrite à son ouverture

`TenantDatabaseMiddleware` enveloppe le pilote de la connexion configurée. Chaque fois que la connexion s'ouvre, son
pilote demande ses paramètres à `TenantDatabaseRouter` : ceux de la connexion partagée tels que configurés, ou, pour un
locataire dédié, les mêmes paramètres avec le nom de base du locataire — et le compte, l'hôte et le port de
`TenantCredentials` quand il les donne. Rien n'est décidé à la compilation du conteneur : le service de connexion est
celui que DoctrineBundle déclare, et un locataire ajouté à l'exécution n'exige aucun déploiement.

Un primaire et ses réplicas arrivent au pilote un nœud à la fois : chaque nœud s'ouvre sur la base du locataire.

## Suivre le locataire actif

`TenantConnectionSwitch` est une activation de locataire de `phil/tenancy-bundle` : `TenantContext` l'appelle chaque
fois qu'une requête, un message ou une commande entre dans un locataire et en sort.

| Événement | Ce qui se passe |
|---|---|
| Un locataire est entré | Son emplacement est lu dans `TenantPlacement`. S'il est dans une autre base que la connexion, la connexion est fermée et routée vers elle ; elle s'ouvre sur la nouvelle base à la requête suivante. |
| Le même locataire est entré de nouveau | Rien. |
| Un autre locataire de la même base est entré | La connexion reste ouverte ; les gestionnaires d'entités sont vidés. |
| Le locataire est quitté | La connexion revient à la base partagée, où le filtre Doctrine ne montre rien sans locataire. |
| `kernel.reset` | La même chose que quitter : la requête suivante d'un *worker* commence dans la base partagée. |

L'emplacement est lu à chaque entrée, jamais conservé : une fois un locataire déplacé, la requête, le message ou la
commande suivante le trouve dans sa nouvelle base, dans chaque processus.

## Transactions

Une transaction appartient à la base où elle a commencé. Entrer dans un locataire d'une autre base pendant qu'une
transaction est ouverte lance `TenantSwitchInTransaction`, et la connexion reste où elle est : validez ou annulez
d'abord. Un locataire de la même base est entré dans la transaction.

Quitter un locataire alors qu'une transaction est encore ouverte ne peut pas être refusé — cela se produit dans un
`finally` et à `kernel.reset` — ; la connexion est donc fermée avec la transaction, que le serveur annule, et un
avertissement est journalisé.

## La table d'identité

Chaque changement de locataire vide les gestionnaires d'entités dont la connexion est celle qui est gérée.
`EntityManager::find()` lit la table d'identité avant la base : sans ce vidage, une entité chargée pour un locataire
serait remise au suivant qui demande la même clé, d'où qu'elle vienne. Un gestionnaire d'entités sur une autre
connexion est laissé tranquille.

Les changements non synchronisés avant un changement de locataire sont écartés avec la table d'identité : synchronisez
dans le locataire.

## Les *workers*

Le routeur et l'aiguillage gardent la base courante, et les deux sont réinitialisés avec le noyau : un *worker*
FrankenPHP ou Messenger commence chaque unité de travail sur la base partagée, avec des gestionnaires d'entités
propres. Rien d'autre n'est conservé entre les requêtes.

## Messenger

Un message ne porte que la référence du locataire, dans l'estampille de `phil/tenancy-bundle`, jamais un nom de base
ni des identifiants. Le *worker* entre dans le locataire de chaque message reçu, et la connexion suit son emplacement
tel qu'il est quand le message est traité, non tel qu'il était à l'envoi. Avec `RefuseUnavailableTenantMiddleware`, un
message d'un locataire suspendu lance `TenantDatabaseUnavailable` et est réessayé par la stratégie de nouvelle tentative
du transport.

## Le cache

Les pools de cache de `phil/tenancy-bundle` sont limités par la référence du locataire, non par sa base : un locataire
qui se déplace garde son cache, qui correspond toujours à ses données, copiées telles quelles.

## Sécurité de ligne

Pour la base partagée, la sécurité de ligne de PostgreSQL que propose `phil/tenancy-bundle` reste une seconde barrière
derrière le filtre Doctrine : sa variable de session est posée sur chaque connexion que la connexion gérée ouvre,
dédiée ou partagée, et une base dédiée peut activer les mêmes politiques. Les connexions d'un déplacement sont ouvertes
dans le locataire, si bien que les politiques laissent passer ses lignes.
