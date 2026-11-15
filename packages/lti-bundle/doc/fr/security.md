<!-- phil-dev source-sha256=68df5706d3410d143bce4fe14f1a729ca10e41be4183ba8dca59eb91e03bb28d -->
# Sécurité

Les vérifications de `phil/lti` — signatures par `RS256` seulement, émetteurs et audiences, heures, nonces et états
employés une seule fois, déploiement, portées — s'appliquent comme le décrit sa page de sécurité. Le bundle ajoute :

## Routes et pare-feu

- La route du lancement vérifie chaque lancement avec l'authentificateur avant que tout code de l'application
  s'exécute ; un lancement refusé répond 401 avec la raison, jamais le jeton.
- Le point de terminaison d'autorisation de la plateforme ne répond qu'à un membre connecté, et qu'à un client et une
  adresse de redirection enregistrés ; les `LaunchMessages` de l'application décident de ce que le membre peut lancer.
- Le point de terminaison des jetons et les services ne prennent ni témoin ni session : mettez-les dans un pare-feu
  avec `security: false`. Chaque appel porte un jeton porteur de sa portée ; les jetons sont gardés par empreinte.
- Les services lisent un corps de 64 Kio au plus et répondent 404 pour un contexte ou un line item qui n'est pas celui
  de l'outil.

## La page qui envoie un lancement par POST

`FormPostResponder` répond avec `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, et un
`Content-Security-Policy` dont le `form-action` est l'origine où elle envoie le formulaire et dont les `frame-ancestors`
sont l'application et cette origine. Elle n'exécute aucun script en ligne : le contrôleur Stimulus envoie le
formulaire, et son bouton reste pour une personne qui lit sans scripts.

## Témoins dans un cadre

Une plateforme affiche souvent l'outil dans un cadre. Le navigateur traite alors les témoins de l'outil comme des
témoins tiers : après un lancement, la session de l'outil ne tient qu'avec `SameSite=None; Secure`, partitionnés lorsque
le navigateur le permet :

```yaml
framework:
    session:
        cookie_samesite: none
        cookie_secure: true
```

Les états de connexion du bundle sont gardés dans la table, non dans un témoin : le lancement lui-même n'en dépend
pas. Là où le navigateur bloque les témoins tiers, ouvrez l'outil dans une fenêtre (`DocumentTarget::Window`).

## Clés

Les clés privées sont scellées au repos par le KMS, ou lues dans l'environnement ; elles n'entrent jamais dans le code
ni dans les fichiers de configuration. Ouvertes, ce sont des JSON Web Keys RSA de 2048 à 8192 bits avec lesquelles `web-token/jwt-library` signe ; une clé hors
de ces bornes est refusée à sa lecture. Renouvelez-les avec `phil:lti:rotate-keys --if-due`.

## Requêtes sortantes

Chaque requête que le bundle envoie passe par `phil/safe-http` : le point de terminaison des jetons, les Assignment and
Grade Services et le Names and Role Provisioning Service d'une plateforme par `SafeHttpClient::send()` — HTTPS
seulement, réseaux privés et réservés refusés, aucune redirection, l'en-tête `Authorization` jamais envoyé hors de
l'origine, 20 secondes et 4 Mio au plus —, les jeux de clés de l'autre côté par `SafeHttpClient::get()`, 10 secondes et
256 Kio au plus, lus au plus une fois par minute par adresse. Le client HTTP de `http_client` est enveloppé par les deux,
jamais appelé directement.

## Messages

Le message `PublishScore` ne porte que l'identifiant d'une note en attente : la note, et l'apprenant qu'elle nomme,
restent dans la table jusqu'à leur envoi.

## Ce qui reste à l'application

Les pare-feu et le contrôle d'accès de ses propres pages ; ce que la plateforme partage d'un membre avec chaque outil ;
l'assainissement du HTML des éléments de contenu qu'elle affiche ; la purge, par `phil:lti:purge`.
