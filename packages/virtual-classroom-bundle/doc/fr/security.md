<!-- phil-dev source-sha256=cdc682ef2a23f20a664f95338ee4e85adcc97f9844d1292088095a9bac4a92ae -->
# Sécurité

## Le webhook

- Chaque adresse porte une preuve HMAC-SHA-256 de son fournisseur, de sa salle et de son canal sous `webhook_secret`,
  comparée en temps constant ; une preuve fausse donne un 404, comme une adresse qui n'existe pas.
- L'adresse des événements signés d'une salle ne peut ni la terminer ni atteindre une autre salle ; une notification
  qui nomme une autre salle que son adresse est refusée.
- Le fournisseur vérifie ensuite sa propre signature : les jetons de BigBlueButton, le `X-Jaas-Signature` de JaaS à
  moins de cinq minutes.
- Un corps fait 1 Mio au plus ; les lecteurs de phil/virtual-classroom bornent le reste.
- Changer `webhook_secret` change chaque adresse : les salles ouvertes à ce moment perdent leurs rappels, et
  `phil:virtual-classroom:sync` les compense.

## Le lien d'accès

- Le lien du serveur est écrit pour le participant de la requête et son rôle, et envoyé par une redirection 303 qui
  n'est pas mise en cache et ne nomme aucun référent.
- Un participant est nommé par l'identifiant stable de son compte ; une adresse courriel ne nomme personne.
- Un GET ouvre la salle : l'appel est idempotent, et la salle appartient à l'application.

## Renseignements personnels

- Les séjours sont des renseignements personnels : exportés et effacés par phil/privacy-bundle.
- Aucun nom d'affichage n'est conservé ; aucun journal ne nomme un participant.

## Ce qui revient à l'application

- Qui peut rejoindre quelle salle : le lien d'accès laisse entrer quiconque `CurrentParticipant` nomme. Placez la route
  derrière le contrôle d'accès dont les salles ont besoin, ou décidez dans un *voter* de `moderator_role` et dans le
  service de participant.
- La copie d'un enregistrement avant l'expiration de son lien, et sa conservation.
- Les secrets, dans l'environnement ou un coffre, jamais dans le dépôt.
