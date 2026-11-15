<!-- phil-dev source-sha256=05db1468342394777808f6a33150b3257036c6faa1a4306c1c79757d40317ac2 -->
# Appareils et sessions

Le module `sessions` lie chaque session PHP à une ligne de `phil_identity_session` : un identifiant opaque de 256 bits
que la session PHP détient, l'appareil — un navigateur et un système lus grossièrement dans l'agent utilisateur, comme
« Firefox sur Linux », jamais l'adresse de la connexion —, les méthodes prouvées, si la connexion avait l'air risquée, et
le moment où elle a été ouverte, vue pour la dernière fois et fermée.

## Ce que fait SessionListener

- Une première connexion ouvre une ligne ; une connexion ultérieure dans la même session — la deuxième étape, une
  réauthentification — y ajoute ses méthodes.
- Chaque requête après le pare-feu voit la ligne, au plus une fois toutes les `sessions.touch_interval` secondes. Une
  ligne fermée depuis un autre appareil met fin à la session PHP sur-le-champ, et la requête se poursuit déconnectée.
- Se déconnecter ferme la ligne.

Une requête sans session préalable ne lit aucun jeton : le pare-feu paresseux reste paresseux, et le cache d'une page
publique reste public.

## La page

`/account/sessions` liste les sessions ouvertes du compte avec leur appareil et le moment où elles ont été ouvertes et
vues pour la dernière fois, la session courante étant marquée par `aria-current`. On en ferme une depuis la page ; on
ferme toutes les autres d'un coup après une preuve récente. Fermer une session révoque les jetons que le fournisseur
OpenID Connect y a émis. Quand le fournisseur est activé, la page liste aussi les applications que le compte a
autorisées, dont on retire le consentement depuis la page, ce qui révoque leurs jetons.

## Connexions risquées

Une connexion depuis un appareil que le compte n'a jamais utilisé, quand il s'est déjà connecté, est risquée : la
session est marquée, l'événement `identity.sign_in.risky` est écrit, et le compte reçoit un courriel qui nomme l'appareil
et l'heure, avec la marche à suivre si ce n'était pas la personne.

## Purge

`phil:identity-provider:purge --session-days=30` retire les sessions fermées et celles inactives depuis plus que le
nombre de jours donné.
