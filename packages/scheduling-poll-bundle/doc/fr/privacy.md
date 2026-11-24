<!-- phil-dev source-sha256=34bdbfaa03cee14f9e559aa63263f31135a6887157fb4dab04bc2c901668f14a -->
# Vie privée

## Ce qui est conservé

| Table | Quoi | Combien de temps |
|---|---|---|
| `phil_scheduling_poll` | Le sondage, son organisateur comme référence | Tant que l'application le conserve |
| `phil_scheduling_poll_slot` | Les dates | Avec le sondage |
| `phil_scheduling_poll_respondent` | Le nom, l'adresse si elle est donnée, le compte s'il y en a un, la langue, le fuseau horaire, les réponses, et quand | Le nom, l'adresse et le fuseau horaire jusqu'à `link_lifetime` après la clôture ; le reste avec le sondage |
| `phil_access_link_invitation`, `phil_access_link` | L'invitation de chaque répondant, nommée par son identifiant, et les empreintes à clé de ses jetons | Comme `phil/access-links` les conserve |

L'adresse n'est demandée que pour envoyer la date choisie, et la page le dit. Aucune adresse, aucun nom ni aucun jeton ne
traverse Messenger : les messages portent des identifiants, et le *worker* lit le reste.

## Les liens

Un jeton est 256 bits aléatoires, montrés une fois et conservés seulement sous forme de HMAC-SHA-256 sous les clés de
`links.keys` : une copie des tables n'ouvre aucun lien sans la clé. Pour renouveler la clé, mettez la nouvelle en
premier et gardez l'ancienne après elle jusqu'à ce que les liens envoyés sous elle aient expiré, `link_lifetime` après
l'échéance de leur sondage. Les pages gardent le jeton hors de l'en-tête `Referer` et hors des caches.

## L'effacement après la clôture

Le planificateur envoie `EraseRespondents` toutes les `erasure.frequency` ; son *handler* efface le nom, l'adresse et le
fuseau horaire des répondants des sondages clôturés depuis plus longtemps que `link_lifetime`, chacun une seule fois.
Les réponses restent, sans personne derrière elles : le tableau des réponses écrit alors un numéro à la place du nom.

## L'export et l'effacement d'une personne

Quand `phil/privacy-bundle` est installé, `SchedulingPollPrivacy` prend part à son export et à son effacement, comme le
module `scheduling_poll`. La personne concernée est soit un compte — une référence du genre `privacy.account_kind`,
`user` par défaut, son identifiant étant le sujet —, soit, pour une personne sans compte, son adresse, comme le bundle
de vie privée nomme un invité par défaut.

- L'export liste chaque répondant de la personne : le sondage et son titre, le nom, l'adresse, le compte, la langue, le
  fuseau horaire, les réponses, et quand il s'est joint, a répondu ou a été révoqué.
- L'effacement supprime ces répondants, rapportés comme `Deleted` pour `phil_scheduling_poll_respondent` ; leurs liens,
  qui ne nomment que le répondant, n'ouvrent plus rien. L'exécuter de nouveau ne trouve rien et rapporte la même chose.
