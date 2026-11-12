<!-- phil-dev source-sha256=c505680eb4819d2fdb69cc830a9e120515c9c50a76da8759dba733973e4951db -->
# Le stockage serveur

Avec `server.enabled`, les brouillons d'un utilisateur connecté sont aussi conservés sur le serveur, pour qu'ils le
suivent sur un autre appareil. Les brouillons d'un visiteur restent dans son navigateur.

## Utilisateurs

`DraftOwner` nomme l'utilisateur : par défaut `TokenDraftOwner`, l'identifiant d'utilisateur de Symfony Security. La
table n'en garde qu'une empreinte à clé, de 64 chiffres hexadécimaux, et chaque lecture ou écriture se fait sous
l'empreinte de l'utilisateur courant : un utilisateur n'atteint donc jamais le brouillon d'un autre, même avec sa clé.

## Révisions et conflits

Chaque enregistrement nomme la révision qu'il remplace, null pour un premier enregistrement. Le serveur conserve le
brouillon par comparaison et échange : une insertion échoue si le brouillon existe, un remplacement si une autre
révision a été enregistrée entre-temps. Dans les deux cas, le point de terminaison répond `409` avec la révision
conservée, et le contrôleur lit le brouillon du serveur et affiche l'avis d'un brouillon plus récent. L'utilisateur
l'utilise, ou garde les valeurs du formulaire, qui le remplacent alors.

## Chiffrement

Chaque brouillon est scellé par `DraftSealer` dans un contexte qui nomme l'empreinte de son propriétaire et sa clé : un
brouillon scellé copié dans une autre ligne ne s'ouvre pas. Avec `server.encrypt` activé, `KeyManagementSealer` scelle
par le chiffrement d'enveloppe du composant KeyManagement de Symfony : une clé de données neuve par brouillon,
enveloppée par la clé maîtresse que nomme `server.key`, le contenu chiffré localement avec AES-256-GCM, le contexte
authentifié avec lui, l'enveloppe enregistrée en base64. L'enveloppe nomme sa clé maîtresse : les brouillons scellés
avant que `server.key` change s'ouvrent encore tant que le KMS détient l'ancienne clé. Un brouillon qui ne s'ouvre plus
— altéré, déplacé, sous une clé maîtresse que le KMS ne détient plus, ou pendant que le KMS est injoignable — est
supprimé à la lecture. Avec `server.encrypt: false`, `PlainSealer` stocke en clair ce que les utilisateurs ont tapé.

## Expiration

Un brouillon est conservé `ttl` secondes après son dernier enregistrement. Un brouillon expiré se lit comme absent, et
un enregistrement par-dessus en commence un nouveau ; `phil:forms-autosave:purge` supprime les brouillons expirés :

```sh
bin/console phil:forms-autosave:purge
```

## La table

`phil_form_draft` : `owner` et `draft_key`, la clé primaire ; `revision` ; `payload`, le contenu scellé ; `saved_at` et
`expires_at`, en UTC, la seconde étant indexée pour la purge. Elle est mappée pour Doctrine quand le stockage serveur
est activé. Le stockage l'écrit par de simples requêtes sur sa connexion, qui laissent le gestionnaire d'entités ouvert
quand une course est perdue.
