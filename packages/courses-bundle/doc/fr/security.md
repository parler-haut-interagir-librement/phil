<!-- phil-dev source-sha256=b6cb7c85f33381bb9a1805c7e4efc13014d71822d9c1c12e76fbdb5ea5656e1c -->
# Sécurité

Un paquet vient d'une autre plateforme, et ses fichiers contiennent des scripts que personne sur ce site n'a écrits.
L'import le lit comme une entrée non fiable, et son contenu s'exécute sur une origine à part.

## L'archive

Le bundle lit les paquets par phil/elearning-packages, dans les limites de `import.limits` ; la documentation de cette
bibliothèque explique chaque vérification dans ses limites et sa sécurité. Son `ZipPackage` vérifie toute l'archive avant
de lire un fichier :

| Vérification | Refusé |
|---|---|
| Taille de l'archive | Au-delà de `import.limits.archive_bytes` |
| Nombre d'entrées | Au-delà de `import.limits.entries` |
| Nom de chaque entrée | Vide, de plus de 1 024 octets, non UTF-8, absolu (`/`, `C:`), avec une barre oblique inverse, un deux-points ou un caractère de contrôle, un segment `.` ou `..`, un segment vide, ou à plus de 32 répertoires de profondeur |
| Noms qui ne diffèrent que par la casse | Le deuxième : sur un disque insensible à la casse, il écraserait le premier |
| Liens symboliques | Toute entrée dont le mode Unix est un lien |
| Entrées chiffrées | Toutes |
| Taille annoncée par chaque entrée, et leur somme | Au-delà de `import.limits.entry_bytes` ou de `import.limits.total_bytes` |
| Taux de compression d'une entrée de plus de 1 Mio | Au-delà de `import.limits.ratio` : une bombe de compression |

Un fichier est ensuite lu par un flux qui s'arrête un octet après ce que son entrée a annoncé : une entrée dont l'en-tête
ment, dont les données se terminent trop tôt ou ne correspondent pas à son CRC-32, est refusée, et les octets lus dans le
paquet comptent dans le même total. Un import qui échoue en cours de route supprime le cours et les fichiers qu'il a
écrits.

Un chemin dans un manifeste est résolu par rapport à ses valeurs `xml:base`, ses séquences d'échappement décodées et ses
segments `.` retirés, puis vérifié de la même façon : une référence qui sortirait du paquet, comme `../../etc/passwd` ou
`%2e%2e/`, fait refuser le paquet.

## Le manifeste

`imsmanifest.xml` et `cmi5.xml` sont lus par l'extension DOM de PHP :

- un document qui déclare un type de document ou une entité est refusé, de sorte qu'aucune entité — interne, externe ou
  imbriquée pour se développer — n'est jamais résolue ;
- l'analyseur n'atteint jamais le réseau ;
- le document, la profondeur de ses éléments ou de ses blocs, et leur nombre sont bornés par `import.limits` ;
- chaque identifiant, titre et URL a une longueur bornée, et un gros élément de séquencement n'est pas conservé.

Les cibles de fuzzing de phil/elearning-packages donnent aux lecteurs des manifestes et des archives des entrées que
personne n'a écrites, et la cible de fuzzing du bundle donne aux vérifications des énoncés les siennes ; voir
[Tests](testing.md).

## L'origine du contenu

Les fichiers sont stockés hors du répertoire public, dans `storage.directory` ou le `ContentStorage` de l'application, et
servis depuis l'origine du contenu de `content.base_url`. Cette origine ne doit pas être celle de l'application : la page
de l'activité refuse d'encadrer un contenu dont l'origine est le site lui-même. Le mieux est un domaine enregistrable à
part, comme `example-content.org` ; un sous-domaine du domaine du site partage son site, et tout témoin de connexion que
l'application fixe pour tout le domaine.

`ContentController` ne répond que sur l'origine du contenu — tout autre hôte est introuvable — et envoie chaque fichier
avec :

| En-tête | Valeur |
|---|---|
| `Content-Type` | Le type de son extension parmi HTML, scripts, styles, JSON, XML, texte, sous-titres, images, audio, vidéo, polices et PDF ; `application/octet-stream` avec `Content-Disposition: attachment` pour tout autre |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `no-referrer` |
| `Content-Security-Policy` | `default-src 'self'` ; scripts et styles de l'origine propre du contenu, en ligne compris, comme l'exige le contenu SCORM ; images, médias et polices de son origine et `data:` ou `blob:` ; `connect-src` son origine et celle de l'application, pour le point de terminaison xAPI ; `object-src 'none'` ; `base-uri` et `form-action` son origine ; `frame-ancestors` l'origine de l'application |

Un serveur Web qui sert lui-même le répertoire de stockage envoie les mêmes en-têtes, sur un hôte qui ne sert rien
d'autre.

## Le cadre

La page de l'activité encadre le contenu avec `sandbox="allow-scripts allow-same-origin allow-forms allow-popups
allow-downloads"`. Le contenu garde sa propre origine — l'origine du contenu, jamais celle de l'application —, dont ses
appels au point de terminaison xAPI ont besoin, et il ne peut pas atteindre la page qui l'encadre.

## L'environnement d'exécution SCORM

Le cadre de l'environnement d'exécution, `_phil/scorm/frame.html` et ses scripts, est servi sur l'origine du contenu
seulement, avec `default-src 'none'`, des scripts de sa propre origine seulement, `frame-src 'self'` et `frame-ancestors`
l'origine de l'application : seule l'application l'encadre, et il n'encadre que l'origine du contenu. Les SCO qu'il
encadre sont servis avec `frame-ancestors 'self'` et l'origine de l'application, afin que le cadre de l'origine du contenu
puisse les contenir.

Le cadre et la page de l'activité se parlent par `postMessage()`, chacun à l'origine de l'autre seulement : le cadre
n'accepte l'état d'une session que de sa fenêtre parente et de l'origine de l'application, la page de l'activité
n'accepte les écritures que de son propre cadre et de l'origine du contenu. L'état contient l'identifiant de l'apprenant,
jamais un nom ni une adresse courriel.

Une validation porte un jeton de tentative — la tentative, la session et une expiration, signées par un HMAC sous
`phil_courses.scorm.secret`, rien de secret conservé dans la base de données — et le jeton CSRF sans état des formulaires :
le `Sec-Fetch-Site`, l'`Origin` ou le `Referer` du navigateur doit dire qu'elle vient du site. Un jeton d'un autre
apprenant connecté, d'une session antérieure, ou expiré est refusé. Le corps est borné à 2 Mio, une validation à 4 000
valeurs, les données d'une tentative à 1 Mio, chaque valeur par le modèle de données de son élément.

## Le point de terminaison cmi5

Le point de terminaison xAPI ne répond qu'à une requête qui porte le jeton d'une session ouverte, et seulement pour
l'acteur, l'enregistrement et l'activité de cette session. Le jeton est récupéré une seule fois. Les requêtes d'origine
croisée reçoivent une réponse pour l'origine du contenu et `cmi5.allowed_origins`, jamais pour une origine quelconque.
Un énoncé qui enfreint une règle de cmi5 est refusé avant d'atteindre l'entrepôt de traces d'apprentissage, et les
apprenants sont nommés par compte, jamais par adresse courriel.

## L'entrepôt de traces d'apprentissage distant

Les énoncés ne vont à un entrepôt distant que par phil/safe-http : un point de terminaison `https` d'un hôte public, son
adresse et celle en laquelle il se résout vérifiées contre les réseaux privés, aucun mandataire, aucune redirection
suivie, le silence et la durée de chaque requête bornés. Le mot de passe est lu dans une variable d'environnement et
envoyé comme identifiants HTTP Basic à l'origine du point de terminaison seulement. Ni le journal ni les exceptions ne
nomment un énoncé, un enregistrement, un apprenant, les identifiants de connexion ni ce que l'entrepôt a répondu : ils
nomment l'identifiant de l'énoncé et le statut. Les documents d'état des unités restent dans la base de données de
l'application et ne sont jamais envoyés à l'entrepôt distant. Un entrepôt sur un réseau privé est refusé, comme
l'explique [cmi5](cmi5.md#les-limites-de-lentrepôt-distant).

## La page d'administration

La page des secondes chances demande le rôle de `phil_courses.admin.role`, par le vérificateur d'autorisation de Symfony
Security ; sans vérificateur, ou sans enseignant connecté, elle est refusée. Chaque décision porte le jeton CSRF sans état
des formulaires, et une requête que le navigateur dit venir d'un autre site est refusée. La raison qu'un apprenant a
donnée et la note d'un enseignant sont conservées dans les tables, échappées sur la page, et jamais écrites dans la piste
d'audit, qui ne garde que des identifiants.
