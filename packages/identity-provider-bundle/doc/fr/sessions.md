<!-- phil-dev source-sha256=9a62ecf17803e13df6e41cce1b58ed7b1042bfb6bfb52972ede844094c9be293 -->
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

## Quand une session se termine d'elle-même

Une session se ferme après `sessions.idle_timeout` secondes sans requête — 7200, deux heures, par défaut — et
`sessions.max_age` secondes après son ouverture, quelle que soit son activité — 43200, douze heures, par défaut :

```yaml
phil_identity_provider:
    sessions:
        idle_timeout: 7200   # 0 for no bound
        max_age: 43200       # 0 for no bound
```

`Sessions::touch()` vérifie les deux bornes avant d'enregistrer la requête, et `Sessions::openFor()` avant de lister les
sessions d'un compte. Une session au-delà de l'une ou l'autre borne est fermée comme depuis un autre appareil : ses
jetons sont révoqués, la ligne fermée, l'événement `identity.session.closed` écrit avec la raison, `idle_timeout` ou
`max_age`, et la session PHP qui la détient prend fin à sa requête, qui se poursuit déconnectée. Se reconnecter ouvre
une nouvelle session.

La dernière requête n'est écrite qu'une fois toutes les `sessions.touch_interval` secondes au plus, de sorte qu'une
session inactive peut se fermer jusqu'à ce nombre de secondes avant `sessions.idle_timeout` ; le délai d'inactivité est
plus long que l'intervalle, et le bundle refuse une configuration où il ne l'est pas. La session PHP elle-même vit selon
les réglages du framework (`framework.session.gc_maxlifetime`, `cookie_lifetime`) ; les bornes du bundle tiennent quels
que soient ces réglages.

Une session au-delà d'une borne et jamais touchée de nouveau par son navigateur est fermée quand le compte liste ses
sessions, ou quand un client du fournisseur OpenID Connect actualise un jeton émis dans cette session. Un jeton de
rafraîchissement lié à une session la touche, comme le fait une requête :

- au-delà de `sessions.idle_timeout` ou de `sessions.max_age`, la session est fermée sur-le-champ, avec tous les jetons
  émis dans cette session, et l'actualisation est refusée avec `invalid_grant` ; la requête suivante de son navigateur
  se poursuit déconnectée ;
- dans ces bornes, l'actualisation compte comme l'activité de la session : une personne qui continue d'utiliser une
  application connectée par le fournisseur garde la session ouverte sans revenir au fournisseur, jusqu'à
  `sessions.max_age`, qu'aucune activité ne prolonge ;
- aucun jeton de rafraîchissement émis dans une session n'expire après `sessions.max_age`, ni après
  `oidc.session_max_age` quand il est défini.

Un jeton de rafraîchissement d'une session fermée est refusé, et sa famille révoquée (voir
[Fournisseur OpenID Connect](oidc-provider.md#jetons-dactualisation-et-session)).

Une requête sans session préalable ne lit aucun jeton : le pare-feu paresseux reste paresseux, et le cache d'une page
publique reste public.

## La page

`/account/sessions` liste les sessions ouvertes du compte avec leur appareil et le moment où elles ont été ouvertes et
vues pour la dernière fois, la session courante étant marquée par `aria-current`. On en ferme une depuis la page ; on
ferme toutes les autres d'un coup après une preuve récente. Fermer une session révoque les jetons que le fournisseur
OpenID Connect y a émis, puis ferme la ligne, dans une seule transaction du contrat `Transactions` —
`DoctrineTransactions` sur le gestionnaire d'entités des stockages. Sans transaction, un échec laisse la ligne ouverte,
de sorte que la fermer de nouveau termine le travail. Un nouveau mot de passe ferme toutes les autres sessions du
compte, un mot de passe réinitialisé toutes (voir [Comptes et mots de passe](accounts.md)). Quand le fournisseur est
activé, la page liste aussi les applications que le compte a autorisées, dont on retire le consentement depuis la page,
ce qui révoque leurs jetons.

## Connexions risquées

Une connexion depuis un appareil que le compte n'a jamais utilisé, quand il s'est déjà connecté, est risquée : la
session est marquée, l'événement `identity.sign_in.risky` est écrit, et le compte reçoit un courriel qui nomme l'appareil
et l'heure, avec la marche à suivre si ce n'était pas la personne.

## Purge

`phil:identity-provider:purge --session-days=30` retire les sessions fermées et celles inactives depuis plus que le
nombre de jours donné.
