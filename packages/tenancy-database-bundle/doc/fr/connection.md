<!-- phil-dev source-sha256=d43dbeaa43d99eca87c34bf81adf121b9cd9c6128026775505004480559a96c0 -->
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
| La fin d'une requête, d'une commande ou d'un message | Une transaction encore ouverte est annulée, puis le locataire est quitté (voir [Les *workers*](#les-workers)). |
| `kernel.reset` | La même chose que quitter : la requête suivante d'un *worker* commence dans la base partagée. |

L'emplacement est lu à chaque entrée, jamais conservé : une fois un locataire déplacé, la requête, le message ou la
commande suivante le trouve dans sa nouvelle base, dans chaque processus.

## Transactions

Une transaction appartient à la base où elle a commencé. Entrer dans un locataire d'une autre base pendant qu'une
transaction est ouverte lance `TenantSwitchInTransaction`, et la connexion reste où elle est : validez ou annulez
d'abord. Un locataire de la même base est entré dans la transaction.

Une transaction encore ouverte quand une requête, une commande ou un message se termine est annulée par
`RollBackOpenTransactions`, avant que le locataire soit quitté (voir [Les *workers*](#les-workers)).

Quitter un locataire alors qu'une transaction est encore ouverte ne peut pas être refusé — cela se produit dans un
`finally` et à `kernel.reset` — ; la connexion d'une base dédiée est donc fermée avec la transaction, que le serveur
annule, et un avertissement est journalisé.

## La table d'identité

Chaque changement de locataire vide les gestionnaires d'entités dont la connexion est celle qui est gérée.
`EntityManager::find()` lit la table d'identité avant la base : sans ce vidage, une entité chargée pour un locataire
serait remise au suivant qui demande la même clé, d'où qu'elle vienne. Un gestionnaire d'entités sur une autre
connexion est laissé tranquille.

Les changements non synchronisés avant un changement de locataire sont écartés avec la table d'identité : synchronisez
dans le locataire.

## Les *workers*

Un *worker* FrankenPHP, un *worker* Messenger ou une console de longue durée sert une unité de travail après l'autre
dans le même processus, avec les mêmes services. Le routeur et l'aiguillage gardent la base courante, et rien d'autre
n'est conservé entre les unités de travail. Chaque unité de travail se termine en deux étapes.

### À la fin : terminate

`RollBackOpenTransactions` écoute les événements qui terminent une unité de travail :

| Événement | Quand il s'exécute |
|---|---|
| `kernel.terminate` | Après l'envoi de la réponse, après les écouteurs propres à l'application (priorité `-2048`). |
| `console.terminate` | Avant que `phil/tenancy-bundle` quitte le locataire de la commande. |
| `WorkerMessageHandledEvent`, `WorkerMessageFailedEvent` | Après qu'un message est traité ou a échoué (priorité `-2048`). |

Pour la connexion qui suit le locataire, et pour `phil_tenancy_database.store_connection` quand le bundle la déclare :

1. Une transaction encore ouverte est annulée, autant de fois qu'elle est imbriquée, et un avertissement est journalisé.
   L'avertissement nomme le locataire, l'actif ou celui dont le message portait l'estampille, et rien d'autre : ni
   instruction, ni ligne, ni hôte, ni compte.
2. Une connexion qui ne peut pas annuler, comme une connexion que le serveur a coupée, est fermée : le serveur annule ce
   qu'il avait d'ouvert, et l'instruction suivante ouvre une nouvelle connexion.
3. Une connexion jamais ouverte dans le processus n'est pas créée ; une connexion sans transaction, ou fermée, est
   laissée telle quelle.

Ensuite, le locataire est quitté : la connexion revient à la base partagée et les gestionnaires d'entités de la
connexion sont vidés. Une requête qui lance une exception au milieu d'une transaction ne laisse donc ni ses écritures
ni ses verrous à la suivante, ce que `kernel.reset` seul n'atteindrait qu'au début de la requête suivante.

Une connexion sans validation automatique commence une nouvelle transaction après chaque annulation : son travail non
validé à la fin de l'unité de travail est écarté.

### Au début : reset

`kernel.reset` s'exécute quand la requête suivante commence. Le routeur, l'aiguillage et le contexte de locataire de
`phil/tenancy-bundle` sont réinitialisés, ce qui ramène encore une fois la connexion à la base partagée, et la
connexion du stockage est fermée, pour qu'un *worker* ne lise jamais par une connexion que le serveur a coupée pendant
qu'il attendait. La requête, le message ou la commande suivante commence sur la base partagée, avec des gestionnaires
d'entités propres et aucune transaction.

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
