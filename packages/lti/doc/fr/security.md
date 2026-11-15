<!-- phil-dev source-sha256=63486ba0036c96f190968fcef8f41c3e65c5b48f1a5f5e650af3299465fb56e9 -->
# Sécurité

Ce que chaque vérification du paquet empêche, et ce qui reste à l'application.

## Jetons

| Vérification | Contre |
|---|---|
| `RS256` seulement, quoi que dise l'en-tête ; la clé choisie par le côté, par `kid` | Les jetons signés avec `none`, ou avec HMAC et la clé publique comme secret. |
| Un jeu de clés de l'adresse enregistrée seulement | Les jetons signés par une clé que publie un faussaire. |
| `iss` et `aud` vérifiés avant la signature | Les jetons d'une autre plateforme ou destinés à un autre client qui atteignent le jeu de clés. |
| `azp` le client lorsqu'il est présent, exigé lorsque `aud` en liste plusieurs | Les jetons destinés à une autre partie d'une audience partagée. |
| `exp`, `iat` pas plus vieux que dix minutes, `nbf`, par une horloge PSR-20 avec cinq secondes de tolérance | Les jetons vieux ou dont l'heure est falsifiée. |
| `nonce` lié à l'état de connexion, employé une seule fois | Les lancements rejoués. |
| `state` pris une seule fois du magasin, expirant après dix minutes | Les lancements sans connexion, la falsification de requête intersite de la connexion. |
| `jti` d'une assertion de client employé une seule fois | Les assertions rejouées au point de terminaison des jetons. |
| Jetons de 64 Kio, JSON de 16 niveaux, jeux de clés de 32 clés et 256 Kio au plus | L'épuisement des ressources par une entrée grande ou profonde. |
| Paramètres d'en-tête critiques et charge utile non encodée (`b64`) refusés | Les extensions que le vérificateur n'implémente pas. |
| Forme lue par `Jwt::parse()` avant que `web-token/jwt-library` voie le jeton | Un jeton grand ou profond qui atteindrait sans borne le lecteur JSON et la vérification de signature de la bibliothèque. |

Les messages des exceptions disent quelle vérification a échoué ; aucun ne répète un jeton.

## Adresses et requêtes

Chaque adresse est lue par `phil/url` : aucun identifiant de connexion dans une adresse, aucune adresse IP, aucun
`localhost` hors du développement. Les jeux de clés sont lus par `phil/safe-http` ; les services — le point de terminaison des jetons, les
Assignment and Grade Services, les Names and Role Provisioning Services — sont appelés par `SafeHttpServiceClient`, au-dessus de
`SafeHttpClient::send()` : HTTPS seulement, chaque adresse résolue et refusée lorsqu'elle est privée ou réservée,
aucun mandataire de l'environnement, aucune redirection suivie par défaut, l'en-tête `Authorization` jamais envoyé hors
de l'origine de l'adresse, le silence de l'hôte, la durée totale et les octets de la réponse bornés — 4 Mio par défaut.
Le message d'un échec nomme l'adresse, jamais la valeur d'un en-tête. La cible d'un lancement doit se trouver sur l'origine de l'outil ; l'adresse de redirection d'une requête
d'authentification doit être une adresse que l'outil a enregistrée, comparée en entier.

## Clés

Les signatures sont faites et vérifiées par `web-token/jwt-library` avec `RS256` seulement. Une clé est une clé RSA de
2048 à 8192 bits, dont l'exposant public est impair et d'au moins 3 ; une clé d'un jeu de clés qui nomme un autre
algorithme ou un autre usage est ignorée. Un vidage d'une clé privée n'en montre que l'identifiant et la moitié publique.

## Jetons d'accès

La plateforme donne un jeton d'accès une seule fois et ne garde que son SHA-256 ; un jeton n'accorde que les portées
demandées qui sont enregistrées pour l'outil ; les services vérifient la portée de chaque appel et que l'outil est
placé dans le contexte.

## Ce qui reste à l'application

- **La session du lancement.** Un outil affiché dans un cadre de la plateforme dépose ses témoins comme des témoins
  tiers : `SameSite=None; Secure`, partitionnés lorsque le navigateur le permet. Le magasin d'états du paquet ne dépend
  pas des témoins, mais la session de l'application après le lancement, oui.
- **Le membre connecté.** Sur la plateforme, `LaunchIssuer::authorize()` compare l'indice de connexion avec celui que
  l'application donne pour le membre connecté ; l'application décide de ce que le membre peut lancer.
- **Les renseignements personnels.** La plateforme choisit ce qu'elle partage du membre — nom, courriel, photo —, et
  l'outil garde ce qu'il reçoit selon sa propre politique de vie privée. Un sujet est opaque et stable par outil.
- **Le HTML d'un élément de contenu**, que la plateforme assainit avant de l'afficher.
- **Les clés privées**, que l'application garde hors de son code et scellées au repos.
- **La purge** des états, nonces et jetons d'accès expirés dans les magasins persistants.
