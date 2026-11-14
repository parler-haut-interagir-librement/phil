<!-- phil-dev source-sha256=fe8e91ea91ab2fdb971c64d344d7b771439948bb42a6e7490d1f077aca02a08e -->
# Pages

Chaque page est une page EasyAdmin de chaque tableau de bord, dans sa mise en page, et demande `PHIL_AUDIT_VIEW`;
l'historique d'un enregistrement demande `PHIL_AUDIT_HISTORY`, et l'export `PHIL_AUDIT_EXPORT`. EasyAdmin nomme chaque
route d'après celle du tableau de bord : sous un tableau de bord dont la route est `admin` à `/admin`, voici les
routes.

| Route | Chemin | Méthode | Page |
|---|---|---|---|
| `admin_audit_log` | `/admin/audit` | `GET` | Le journal, le plus récent d'abord, avec ses filtres |
| `admin_audit_row` | `/admin/audit/row/{segment}/{sequence}` | `GET` | Une ligne |
| `admin_audit_history` | `/admin/audit/history/{type}/{id}` | `GET` | L'historique d'un enregistrement, le plus ancien d'abord |
| `admin_audit_chain` | `/admin/audit/chain` | `GET` | Chaque segment avec sa tête |
| `admin_audit_verify` | `/admin/audit/chain/{segment}` | `GET` | Un segment, vérifié par rapport à ses ancres |
| `admin_audit_export` | `/admin/audit/export` | `POST` | Le CSV des filtres du journal |

`AuditViewerAdmin::menuItems($dashboardRoute)` donne les liens vers le journal et vers la chaîne, libellés dans le
domaine `phil_audit_viewer`.

Chaque réponse est privée et non conservée (`Cache-Control: private, no-store`) : le journal n'est gardé ni par un
navigateur ni par un mandataire.

## Le journal

Un tableau de lignes — instant, segment et séquence, action, acteur, enregistrement, locataire —, chaque ligne liée à sa
page et chaque enregistrement à son historique. Les filtres sont les champs d'un formulaire `GET` : un journal filtré
est donc un lien à partager :

| Champ | Filtre |
|---|---|
| `actor` | L'identifiant stable d'un acteur |
| `action`, `prefix` | Une action, ou, avec `prefix` coché, chaque action qui commence ainsi, comme `workflow.order.` |
| `subject_type`, `subject_id` | Chaque enregistrement d'un type, ou un enregistrement; un identifiant exige son type |
| `segment` | Un segment |
| `tenant` | Les lignes d'un locataire, pour un lecteur hors de tout locataire qui lit tous les sites; dans un locataire, le journal lui reste limité, quoi que dise ce champ |
| `since`, `until` | D'une date et d'une heure locales incluses à une autre exclue, dans le fuseau horaire de `timezone` |

Une valeur qui ne se lit pas — une adresse de courriel comme acteur, une date dans un autre format, un locataire avec un
caractère de contrôle — est signalée au-dessus du formulaire, le champ marqué, et la page répond `400` sans lignes. Une
page contient `page_size` lignes; le lien vers la page suivante porte les filtres et un curseur : paginer coûte donc la
même chose sur la première page et sur la millième.

## Une ligne

Son instant à la microseconde, son genre, ses faits, sa version de sérialisation, son empreinte précédente et son
empreinte, et sa charge utile : les champs qu'un `PayloadReader` lit, ou les octets tels qu'ils sont stockés. Une ligne
qui ne correspond pas à son empreinte — modifiée après son écriture — le dit en haut. Une ligne qui n'existe pas, ou
qui appartient à un autre locataire que le courant, est un `404`.

## L'historique d'un enregistrement

Les lignes d'un enregistrement, la plus ancienne d'abord : les changements d'état que ses workflows ont consignés
(`workflow.order.pay`, avec les places d'où il vient et où il va) à côté de tout autre événement qui le concerne,
chacun avec son acteur et ses champs. Un type d'enregistrement ou un identifiant qui n'en est pas un est un `400`; un
enregistrement dont rien n'a été consigné montre qu'il n'a aucun historique. Un titulaire de `PHIL_AUDIT_HISTORY` sans
`PHIL_AUDIT_VIEW` lit l'historique seul : les lignes ne sont pas des liens vers leurs pages, et le lien qui filtre le
journal sur l'enregistrement n'est pas montré. L'action « Historique » des pages CRUD d'une entité auditée mène ici
([history-action.md](history-action.md)).

## La chaîne

Chaque segment, par nom, avec sa ligne de tête et le moment où elle a été consignée, lus sur la clé sans vérifier. Quand
la piste garde une chaîne par site (`site_segments` de `phil/audit-bundle`), les segments sont regroupés : ceux que
tous les sites partagent d'abord, puis chaque site sous son locataire, chaque segment montré sous le nom que
l'application lui a donné, son nom complet dans son titre. Dans un locataire, seuls les segments de ce site sont
listés, et le segment d'un autre site est un `404`. Vérifier un segment lit chacune de ses lignes et la compare à ses ancres, comme le fait `phil:audit:verify` : c'est donc
un lien à part. La page du segment dit s'il tient, s'il tient avec des ruptures qu'une discontinuité consignée explique,
ou s'il ne tient pas, et liste chaque rupture avec la ligne vers laquelle elle pointe, marquée comme alarme ou comme
expliquée. Un segment sans lignes est un `404`.
