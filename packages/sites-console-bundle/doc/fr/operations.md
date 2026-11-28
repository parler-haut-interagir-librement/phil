<!-- phil-dev source-sha256=26395cedc390ae04f76f39b7d09f7c214431f0e53b76ad36fff7205d86eee9ea -->
# Exploiter les sites

## Enregistrer un site

```sh
php bin/console phil:sites-console:site:add shop https://shop.example.com/_sites-console <public key> \
    --name="Shop" --group=eu --group=web --actor=ada
```

L'identifiant est composé de 1 à 64 lettres minuscules, chiffres et tirets intérieurs, en commençant par une lettre ;
il doit être le `site_id` de l'agent du site. L'adresse est lue par `phil/url` : `https`, un nom d'hôte public, ni
requête ni fragment. La clé publique est celle avec laquelle l'agent du site signe. Relancer la commande avec le même
identifiant modifie le site. `phil:sites-console:site:remove shop` retire un site et ses instantanés ; ses lignes du
journal restent.

Les groupes nomment des ensembles de sites — une région, un client, un genre de site — que les interrogations et les
plans peuvent cibler avec `--group`.

## Interroger

```sh
php bin/console phil:sites-console:pull                 # every site
php bin/console phil:sites-console:pull --site=shop      # one site, repeatable
php bin/console phil:sites-console:pull --group=eu
```

Chaque interrogation demande son `status` à l'agent du site et garde un **instantané** : les versions de PHP et de
Symfony, les paquets et leurs versions, la santé et chaque vérification. Un site qui ne peut pas être lu, qui répond
par une erreur ou dont la réponse n'est pas signée par sa clé reçoit un instantané qui le dit, avec la raison. La
commande se termine par un échec quand un site n'a pas pu être lu.

Pour interroger selon un horaire, répartissez `Application\Message\PullSite` pour chaque site depuis un Scheduler de
l'application, ou lancez la commande depuis cron. Depuis les écrans, « Read every site now » et « Read this site now »
confient le travail à Messenger quand un bus est présent, et l'exécutent tout de suite sinon.

`phil:sites-console:purge` retire les instantanés plus vieux que `snapshot_retention_days`, en gardant le dernier de
chaque site.

## Alertes

Un site demande de l'attention quand :

| Alerte | Quand |
|---|---|
| `unreachable` | Son dernier instantané est une erreur. |
| `health_down`, `health_degraded` | Sa santé est en panne ou dégradée. |
| `version_behind` | Un paquet surveillé est plus ancien que la version la plus récente qu'un autre site exécute, ou que sa version attendue. |

Le tableau de bord montre les alertes des derniers instantanés. Une interrogation qui trouve une alerte que
l'instantané précédent de son site n'avait pas répartit une fois `Application\Event\SiteAlertRaised` : écoutez-la pour
envoyer une notification. Une version en retard parce qu'un autre site a été mis à niveau depuis est montrée sur le
tableau de bord, et levée à la prochaine interrogation qui change le site. Les versions sont comparées dans l'ordre des
versions de Composer ; une branche comme `dev-main` n'a pas d'ordre et ne lève rien.

## Versions côte à côte

L'écran des versions liste les paquets surveillés en regard des sites, la version la plus récente de chacun, et marque
une version plus ancienne que la plus récente.

## Pousser des réglages

```sh
php bin/console phil:sites-console:push '{"mail.footer": "Hello", "banner.text": null}' --site=shop --site=blog
php bin/console phil:sites-console:push --retry=<push id>
```

Une poussée est gardée et journalisée avant que quoi que ce soit soit envoyé. Chaque site reçoit ensuite une requête
signée qui porte l'identifiant de la poussée et les réglages ; son agent les applique par son `ConfigurationApplier`,
une seule fois : une poussée livrée de nouveau répond qu'elle a déjà été appliquée. Un site en échec — injoignable, une
clé qu'il ne permet pas, un applicateur qui a levé une exception — est retenté à la prochaine livraison, et lui seul.
L'écran de la poussée montre chaque livraison ; « Deliver to the sites not reached » livre de nouveau.

Les réglages sont de 1 à 50 clés composées de lettres minuscules, de chiffres, de points, de tirets et de soulignés,
chacune un texte d'au plus 1 000 caractères, un nombre, un booléen ou null.

## Mettre à jour par lots

```sh
php bin/console phil:sites-console:update:plan vendor/package 1.4.2 --site=a --site=b --site=c --batch-size=2
php bin/console phil:sites-console:update:run <plan id>               # every batch left
php bin/console phil:sites-console:update:run <plan id> --batches=1   # the next batch only
```

Un plan nomme un paquet, une version et les sites dans leur ordre, regroupés en lots. L'exécuter interroge l'agent de
chaque site à tour de rôle, l'un après l'autre ; chaque résultat est gardé à mesure. **Le premier échec arrête le
plan** : le site en échec est marqué échoué, chaque site non atteint est sauté, et le plan est arrêté. Un plan qui a
parcouru tous les sites est terminé. L'écran du plan est le rapport, une ligne par site avec son lot, son résultat et le
message de l'agent ; « Run the next batch » exécute un lot, de sorte qu'un opérateur peut vérifier les premiers sites
avant les autres.

Chaque étape porte le plan et le site comme clé d'idempotence : un agent qui l'a déjà exécutée répond de nouveau le
premier résultat.

## Faire la rotation de la clé de la console

```sh
php bin/console phil:sites-console:keys:rotate --actor=ada
```

Sans `console.next_private_key`, la commande affiche une nouvelle paire de clés et s'arrête : mettez la clé privée dans
les secrets de la console sous `console.next_private_key`, déployez la console, puis relancez la commande. Elle dépose
alors la clé publique suivante sur chaque site, fait passer la console à la clé suivante dès que chaque site la détient,
retire la clé précédente de chaque site, et affiche le rapport : une ligne par site, avec l'état de dépôt de la clé
suivante, l'état de retrait de la clé précédente et le message de l'agent.

**Le premier échec arrête la rotation** : réparez le site — ou retirez-le de la console — et relancez la commande ; elle
reprend où elle s'était arrêtée, chaque étape avec la même clé d'idempotence qu'avant. Tant que la clé suivante est
déposée, la console continue de signer avec la clé courante, de sorte que les sites pas encore atteints continuent de
répondre. Une fois la rotation terminée, déplacez `console.next_private_key` vers `console.private_key`, et donnez à
`agent.console_public_key` la nouvelle clé publique sur chaque site à son prochain déploiement. La procédure et la
conduite à tenir quand une clé fuit sont dans [le modèle de sécurité](security-model.md#rotation-des-cles).

## Le journal

Chaque site enregistré ou retiré, chaque poussée créée et chacune de ses livraisons, chaque plan et chacune de ses
étapes, chaque rotation de clé et chacune de ses étapes est une ligne du journal : quand, qui — l'identifiant
d'utilisateur de l'opérateur, ou le `--actor` d'une commande —, quel site, quelle poussée, quel plan ou quelle rotation,
si cela a fonctionné et le message. L'écran du journal montre les
200 dernières lignes ; l'écran d'un site, ses 20 dernières.
