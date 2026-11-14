<!-- phil-dev source-sha256=ead306d963e1b9f6d81a1a65b5bb689633965661618f0ab630bb43fe8e32287c -->
# Accès et export

## Trois attributs

| Attribut | Demandé par, avec quoi | Accordé par `AuditViewerVoter` à |
|---|---|---|
| `PHIL_AUDIT_VIEW` | chaque page; la page d'une ligne aussi avec l'`AuditRow`, la vérification avec le nom du segment; chaque ligne que le journal liste avec son `AuditRow`, chaque segment que la page de la chaîne liste avec son nom | les titulaires de `role`, `ROLE_AUDIT_VIEWER` par défaut |
| `PHIL_AUDIT_HISTORY` | l'historique d'un enregistrement, avec son `Subject`; l'action « Historique », avec le `Subject` de l'enregistrement | les titulaires de `history_role` ou de `role` |
| `PHIL_AUDIT_EXPORT` | l'export, et son bouton sur le journal; chaque ligne que l'export lit, avec son `AuditRow` | les titulaires de `export_role`, le rôle du lecteur quand il vaut `null` |

Le *voter* demande au gestionnaire de décision d'accès si le jeton détient le rôle : une hiérarchie de rôles
s'applique donc, et il donne sa raison au vote — `PHIL_AUDIT_HISTORY is granted to ROLE_ADMIN.`,
`PHIL_AUDIT_VIEW needs ROLE_AUDIT_VIEWER.` —, que le panneau de sécurité du profileur montre. Une fois le rôle détenu, le *voter* interroge l'`AuditAccessPolicy` de
l'application, quand `access_policy` en nomme une, et refuse ce qu'elle refuse — `PHIL_AUDIT_HISTORY is refused by the
access policy to ROLE_ADMIN.` —; il n'accorde rien d'autre, donc il n'élargit jamais l'accès, quelle que soit la
stratégie de décision d'accès ([extension-points.md](extension-points.md)). La page d'une ligne
demande `PHIL_AUDIT_VIEW` avant de lire la ligne, puis avec la ligne : un utilisateur sans l'attribut ne peut pas
distinguer une ligne qui existe d'une ligne qui n'existe pas. Un utilisateur sans l'attribut rencontre le pare-feu, comme sur toute page refusée : `401` sans
session, `403` avec une session. Lire le journal d'enregistrements personnels est un droit à part : donnez le rôle aux
personnes qui répondent de la piste, pas à chaque administrateur.

Dans un locataire — avec `phil/tenancy-bundle` —, les pages ne lisent que les lignes du locataire courant, par
`AuditLog`, et la page de la chaîne que les segments de ce site, par `ChainStatus`, comme l'expliquent `doc/reading.md`
et `doc/sites.md` de `phil/audit-bundle`. Un lecteur hors de tout locataire lit tous les sites, et filtre le journal
sur l'un d'eux avec `tenant`.

## L'export CSV

La page du journal montre le bouton aux titulaires de `PHIL_AUDIT_EXPORT` quand `phil/audit-bundle` a un `AuditTrail`.
Il envoie par `POST` les filtres de la page avec un jeton CSRF d'identifiant `phil_audit_viewer_export`; un jeton
absent ou erroné donne un `400`, tout comme des filtres qui ne se lisent pas.

Avant que la première ligne soit écrite, l'export est consigné dans la piste, dans le segment `export_segment` :

- l'action `audit.export` et l'acteur que donne l'`ActorResolver` — l'identifiant stable d'un utilisateur qui
  implémente `AuditActor` —, ou aucun;
- dans la charge utile, `format`, `limit`, et chaque filtre sous la forme `filter_<field>` : ce qui a été demandé,
  jamais une valeur du journal.

Un téléchargement interrompu se trouve donc lui aussi dans la piste, et un export apparaît dans le journal comme
n'importe quel autre événement : l'export suivant le lit. Le fichier est envoyé en flux, lu 500 lignes à la fois, et
s'arrête après `export_limit` lignes lues; une ligne que la politique d'accès refuse n'est pas écrite, de sorte qu'un
export peut contenir moins de lignes qu'il n'en a lu. Ses colonnes :

`recorded_at`, `segment`, `sequence`, `kind`, `action`, `actor`, `subject_type`, `subject_id`, `tenant`,
`serialization_version`, `payload`, `previous_hash`, `hash`, `intact`

`intact` vaut `yes` ou `no` selon que la ligne correspond ou non à son empreinte. La réponse est une pièce jointe
nommée d'après l'instant (`audit-20261108-110200.csv`), `text/csv` en UTF-8, `nosniff`, privée et non conservée.

Une cellule qui commence par `=`, `+`, `-`, `@`, une tabulation, un retour chariot ou un saut de ligne est écrite
après un `'` : un tableur la lirait comme une formule, et une charge utile écrite par n'importe qui ne doit pas
s'exécuter à l'ouverture du fichier.
