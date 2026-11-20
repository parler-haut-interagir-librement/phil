<!-- phil-dev source-sha256=6f8ebe99a711dd08cdca994bc3751b73de6973eec68339c1ae1db76c260e8bd5 -->
# Modèle de menaces

Ce dont le bundle se défend, comment, et ce qu'il laisse à l'application. Une revue de sécurité par quelqu'un qui n'a pas
écrit le code précède l'utilisation du bundle en production, à partir de cette page et du
[guide du réviseur](security-review.md).

## Actifs

| Actif | Où il se trouve | Ce qui le protège |
|---|---|---|
| Empreintes de mots de passe | `phil_identity_account` | Argon2id ; jamais journalisées, jamais dans un événement. |
| Clés publiques et compteurs des clés d'accès | `phil_identity_passkey` | Publiques par nature ; le compteur doit avancer. |
| Secrets TOTP, empreintes des codes de récupération | `phil_identity_totp`, `phil_identity_recovery_codes` | Scellés par le `SecretCipher` (composant KeyManagement) : le module refuse de démarrer sans clé sauf si `secrets.allow_plaintext_secrets` permet le clair ; codes calculés avec `secret`. |
| Sessions | `phil_identity_session` et la session PHP | Lignes fermées à distance ; Symfony Security migre la session à la connexion. |
| Les clés privées de signature du fournisseur | `phil_identity_signing_key` | La JSON Web Key privée scellée par le `SecretCipher`, liée à son `kid` ; elle ne quitte jamais le serveur. |
| Codes, jetons d'accès et d'actualisation | `phil_identity_code`, `phil_identity_token` | Seuls leur SHA-256 ou leur `jti` sont gardés ; vies courtes ; rotation avec détection de réutilisation. |
| Secrets des clients | `phil_identity_client` | 256 bits aléatoires, gardés sous forme de SHA-256, comparés en temps constant. |
| Le lien entre les personnes et les identités fédérées | `phil_identity_linked` | Jamais fusionné sur une adresse que le fournisseur n'a pas vérifiée. |

## Frontières de confiance

1. **Le navigateur vers les pages du bundle et le point de terminaison d'autorisation.** Tout ce qui est dans une requête,
   un formulaire ou un témoin n'est pas fiable : les paramètres sont bornés (`AuthorizationRequestParser::MAX_PARAMETER`),
   les formulaires portent des jetons CSRF, l'adresse de redirection est vérifiée avant que quoi que ce soit soit renvoyé.
2. **Les clients vers les points de terminaison de jeton, userinfo, révocation et introspection.** Un client est
   authentifié avant que sa requête soit lue ; un jeton porteur est un JSON Web Token dont la signature, l'émetteur et la
   ligne sont tous vérifiés.
3. **Les authentificateurs vers les cérémonies WebAuthn.** CBOR, données d'authentificateur, données du client et clés COSE
   sont lus par des lecteurs bornés, avant toute cryptographie.
4. **Le bundle vers `web-token/jwt-library`.** Chaque JSON Web Signature que le fournisseur écrit ou lit passe par la
   bibliothèque : `JWKFactory` crée les clés, `JWSBuilder` signe, `JWSVerifier` vérifie avec un `AlgorithmManager` qui
   contient l'unique algorithme de la clé, `CompactSerializer` lit et écrit la forme compacte. Le bundle borne l'entrée non
   fiable avant que la bibliothèque la voie (voir « Falsification de jeton » plus bas) et n'enregistre ni `none` ni aucun
   algorithme HMAC.
5. **Le bundle vers OpenSSL et libsodium.** La bibliothèque signe avec OpenSSL (RS256, ES256) et libsodium (EdDSA) ; les
   signatures des clés d'accès sont vérifiées par `Domain\WebAuthn\PublicKey` sur les mêmes extensions.
6. **Le bundle vers la base de données et le composant KeyManagement.** La base de données est fiable pour l'intégrité,
   pas pour le secret de ce que le `SecretCipher` scelle.

## Attaques sur les identifiants

| Menace | Défense |
|---|---|
| Deviner des mots de passe en ligne | Le `login_throttling` du pare-feu par adresse et par identifiant et adresse ; avec `login_limiter`, aussi par identifiant quelle que soit l'adresse, contre les essais répartis sur beaucoup d'adresses ; le verrou progressif par compte, compté sous un verrou de sa ligne et vérifié avant le mot de passe ; les clés d'accès connectent quand même un compte verrouillé. |
| Retenir les connexions d'un compte | Le compte par identifiant et le verrou progressif peuvent être dépensés par quiconque connaît une adresse ; le verrou refuse seulement le mot de passe, le compte dure son intervalle, et `login_limiter.account_max_attempts` reste bien au-dessus des autres limites. |
| Choisir sa propre adresse | Les adresses comptées sont celles que voit Symfony : `framework.trusted_proxies` et `framework.trusted_headers` nomment les seuls mandataires, de sorte qu'aucun client ne règle son adresse par un en-tête. |
| Mots de passe faibles ou divulgués | `phil/passwords` : estimateur de force avec les données propres au compte comme premières hypothèses ; Pwned Passwords par k-anonymat quand il est activé. |
| Base de données de mots de passe volée | Argon2id ; aucun mot de passe dans un journal, un message ou un événement. |
| Hameçonnage | Clés d'accès : le navigateur ne signe que pour la partie de confiance ; origines vérifiées. |
| Code TOTP rejoué | Le compteur accepté est conservé, relu et retenu avant d'être enregistré ; un code ne se vérifie qu'une fois, même présenté par deux requêtes en même temps. |
| Authentificateur cloné | Le compteur de signature doit avancer quand l'authentificateur en tient un. |
| Réponses d'authentificateur malformées | Lecteur CBOR borné : profondeur, éléments et longueurs vérifiés avant l'allocation ; soumis au fuzzing. |
| Énumération de comptes | L'inscription, la réinitialisation du mot de passe et les liens de connexion répondent la même chose pour les adresses connues et inconnues ; la limite d'inscription par adresse compte pareillement les adresses connues et inconnues. |
| Un lien qui mène à un autre hôte | Les liens des courriels sont construits sur `public_origin`, jamais sur l'en-tête `Host` de la requête ; sans lui, `framework.trusted_hosts` refuse un hôte falsifié, et dans l'environnement `prod` le bundle refuse de démarrer quand ni l'un ni l'autre n'est réglé. |
| Inscription en masse | Par défaut, l'inscription est comptée par adresse de client et par adresse saisie, et un seul courriel de vérification va à une adresse à chaque `resend_delay`. |
| Deviner le code de la deuxième étape | Cinq codes erronés de suite ferment la session : les essais suivants exigent de nouveau le mot de passe, sous les limites du pare-feu et le verrou progressif. |
| Liens réutilisés | Liens signés sur les propriétés qu'ils changent — l'adresse et sa vérification, l'empreinte du mot de passe et sa date —, de sorte que chacun ne fonctionne qu'une fois sans rien stocker ; un lien de réinitialisation pose son mot de passe sous un verrou de la ligne du compte, de sorte que deux requêtes au même moment ne peuvent pas toutes deux s'en servir. |
| Un lien ouvert par un analyseur de courriel | `/verify-email` affiche seulement une page qui demande de confirmer ; l'adresse est vérifiée par le formulaire qu'elle envoie, avec un jeton CSRF. |

## Attaques sur les sessions

| Menace | Défense |
|---|---|
| Fixation de session | Symfony Security migre la session à la connexion. |
| Un appareil volé | Les sessions fermées depuis un autre appareil prennent effet à la requête suivante ; les jetons émis dedans sont révoqués, puis la session fermée, dans une seule transaction. |
| Une session volée après un changement de mot de passe | Un mot de passe nouveau ou réinitialisé ferme les autres sessions et leurs jetons ; `AccountUser::isEqualTo()` compare l'empreinte du mot de passe, de sorte qu'une session ouverte avant le changement prend fin à sa requête suivante. |
| Une session ouverte depuis longtemps utilisée pour une action sensible | `PHIL_STEP_UP` demande une preuve récente de la méthode la plus forte. |
| Une session mémorisée qui répond à `max_age` | Une session qui ne détient aucune preuve, comme celle que restaure `remember_me`, s'ouvre de nouveau quand un client demande `max_age`. |
| Un deuxième facteur contourné | La deuxième étape est imposée à chaque requête jusqu'à ce qu'une preuve forte soit sur le jeton ; seules les routes nommées exactement restent ouvertes — le défi, son code, la connexion par clé d'accès et `open_routes` —, aucun préfixe. |
| Falsification de requête inter-sites | Chaque formulaire et chaque requête de script porte un jeton CSRF ; la déconnexion depuis un lien demande de confirmer. |

## Attaques sur le fournisseur

| Menace | Défense |
|---|---|
| Redirection ouverte, vol de code par une adresse de redirection | Correspondance exacte avec une adresse enregistrée, lue par `phil/url` ; erreurs sur le client montrées, non redirigées. |
| Interception ou injection de code | PKCE S256 exigé de chaque client public et, par défaut, de chaque client confidentiel ; un code émis sans défi refuse un vérificateur au point de terminaison de jeton (RFC 9700, section 2.1.1) ; codes hachés, de courte durée, utilisés une fois, la réutilisation révoquant la famille. |
| Vol de jeton d'actualisation | Rotation par une seule écriture conditionnelle, avec détection de réutilisation qui révoque la famille, même entre deux requêtes simultanées ; un jeton de rafraîchissement lié à sa session par défaut, refusé une fois la session terminée. Voir [Familles de jetons](#familles-de-jetons). |
| Usurpation d'un client par un secret volé | `private_key_jwt` : le client garde une clé privée que le fournisseur ne voit jamais ; `oidc.client_authentication.methods` peut refuser tout à fait les secrets. |
| Une assertion de client rejouée | `jti` enregistré jusqu'à l'expiration de l'assertion, le décalage ajouté, sous une clé qui refuse la seconde de deux requêtes simultanées ; `exp` obligatoire et au plus `assertion_max_lifetime` devant ; `aud` l'émetteur ou le point de terminaison des jetons à l'origine de l'émetteur, jamais une adresse construite à partir de l'en-tête `Host`. |
| Un jeton employé à une autre ressource | Un jeton nomme sa ressource dans `aud`, une ressource que le client a enregistrée ; userinfo refuse un jeton d'une autre audience et tout jeton de l'octroi `client_credentials`. |
| Falsification de jeton | Signatures asymétriques seulement — RS256, ES256, EdDSA. Avant la bibliothèque : un jeton d'au plus `Jws::MAX_LENGTH` octets, trois parties, un en-tête et des revendications qui sont des objets JSON de profondeur bornée, un en-tête qui nomme un algorithme et un `kid`, ni `crit` ni `b64`. Ensuite le `kid` doit nommer une clé publiée, l'algorithme de l'en-tête doit être l'`alg` propre à la clé, et la bibliothèque vérifie avec cet algorithme seul : `none`, HMAC et la confusion d'algorithmes sont refusés. |
| Clés trop faibles ou d'un mauvais genre | Nouvelles clés : RSA de 3072 bits, P-256, Ed25519, créées par `JWKFactory`. Une clé relue du magasin doit correspondre au type et à la courbe de son algorithme et contenir tous ses membres privés ; un module RSA de moins de 2048 bits est refusé. |
| Objets de requête | Non acceptés : `request` et `request_uri` sont refusés avec `request_not_supported` et `request_uri_not_supported` avant que quoi que ce soit les lise. Le seul JSON Web Token qu'un client envoie est une assertion de client, bornée comme chaque jeton avant que la bibliothèque la voie, et vérifiée seulement avec une clé publique que le client a enregistrée. |
| Confusion entre fournisseurs | L'émetteur dans la réponse d'autorisation (RFC 9207). |
| Plusieurs clés actives à la fois | La première clé et chaque rotation sous un seul verrou partagé ; les clés actives ou en attente en trop réglées à chaque lecture. |
| Compromission de clé | Rotation avec publication avant l'emploi ; `rotate-keys --now` ; clés privées scellées par le composant KeyManagement quand il est configuré. |
| Un client qui lit les jetons d'un autre | L'introspection et la révocation ne répondent que pour les jetons du client qui demande. |
| Un compte désactivé qui garde son accès | L'introspection répond `active: false` pour les jetons d'un compte désactivé, comme userinfo les refuse. |
| Un client désactivé qui garde son accès | Un client désactivé ne peut pas s'authentifier aux points de jeton, de révocation et d'introspection, et userinfo refuse les jetons qui lui ont été émis. |
| Un client resté connecté après la fin de la session | Avec `oidc.backchannel_logout`, chaque client à qui la session a émis des jetons reçoit un jeton de déconnexion signé, réessayé par Messenger jusqu'à ce qu'il réponde ; le message ne porte que des identifiants, et le jeton est signé à l'envoi. |
| Une adresse de déconnexion employée pour atteindre le réseau du fournisseur | L'adresse est enregistrée par la console, `https` et lue par `phil/url` ; aucune redirection n'est suivie, et chaque tentative est bornée par `oidc.backchannel_logout.timeout`. |
| Un client qui demande un sélecteur de compte | Le fournisseur n'en affiche aucun : `prompt=select_account` est refusé avec `invalid_request` et n'est pas dans le document de découverte, de sorte qu'aucun client ne prend le compte courant pour un compte choisi. |

## Familles de jetons

Une famille est l'ensemble des jetons émis à partir d'un même code d'autorisation, chaque renouvellement de son jeton
d'actualisation compris. C'est l'unité que le fournisseur révoque quand il voit le signe d'un vol.

| Menace | Défense |
|---|---|
| Un jeton d'actualisation volé employé après que le client l'a renouvelé | Le jeton renouvelé présenté de nouveau révoque la famille — les jetons du voleur et ceux du client —, et `identity.oidc.token_reuse` est journalisé avec la famille. |
| Un jeton d'actualisation volé employé avant le client | L'actualisation du voleur renouvelle le jeton ; la prochaine actualisation du client présente le jeton renouvelé et révoque la famille, avec les nouveaux jetons du voleur. Le voleur garde un jeton d'accès jusqu'à son expiration, `oidc.access_token_lifetime`. |
| Deux actualisations qui se disputent le renouvellement d'un jeton | Un seul `UPDATE` conditionnel nomme l'unique gagnante ; l'autre est une réutilisation. Les tests jouent deux requêtes entrelacées et deux connexions à la base de données. |
| Une famille qui survit à la fin de sa session | La fermeture de la session révoque les jetons émis en elle ; un jeton d'actualisation d'une session fermée, purgée ou trop vieille est refusé et révoque sa famille. |
| Une famille révoquée rejouée | Un jeton d'actualisation révoqué n'est jamais actif de nouveau : un rejeu est refusé, sans nouvelle famille. |

## Liaison de comptes

Une identité externe ne fusionne jamais dans un compte sur une adresse que son fournisseur n'a pas vérifiée ; sur une
adresse vérifiée, seulement quand l'option est activée et que le compte l'a vérifiée aussi. Délier conserve une façon de
se connecter.

## Vie privée

Le bundle ne garde aucune adresse de connexion, seulement un nom d'appareil grossier, aucun jeton d'un fournisseur
fédéré. Avec `oidc.subject.type: pairwise`, des clients de secteurs différents reçoivent des sujets différents pour un
même compte et ne peuvent pas rapprocher leurs personnes par lui ; avec `public`, l'identifiant interne ne quitte jamais
le fournisseur. Ses événements et ses lignes d'audit portent des identifiants, jamais une adresse, un nom, un code ou un
secret.

## Ce que l'application garde

Les limites de débit du pare-feu, la sécurité du transport (HTTPS, HSTS, témoins sécurisés), les en-têtes de sécurité,
le coffre à secrets qui détient `secret`, les contrôles d'accès de la base de données, la politique de sauvegarde et
d'effacement, et la revue de ses propres gabarits quand elle remplace ceux du bundle.

## Risques ouverts

| Risque | Pourquoi il reste | Ce qui le réduit |
|---|---|---|
| Un client confidentiel enregistré avec `--pkce-optional` | Le profil Basic d'OpenID Connect permet à un client confidentiel de se passer de PKCE, et la suite de conformité joue un tel client. | Désactivé par défaut ; un tel client envoie un `nonce`, que le jeton d'identité rapporte ; un client public ne peut jamais s'en passer. |
| Des secrets gardés en clair exprès | Avec `secrets.allow_plaintext_secrets: true`, les JSON Web Keys privées et les secrets TOTP sont gardés en clair dans la base de données. | Désactivé par défaut : sans elle, les modules `totp` et `oidc` refusent de démarrer sans `secrets.kms_key` ; restreignez l'accès à `phil_identity_signing_key` et `phil_identity_totp`. |
| Des secrets écrits avant la clé | Régler `secrets.kms_key` chiffre ce qui est écrit à partir de là ; les lignes écrites en clair avant restent lisibles en clair, et le chiffreur les ouvre encore. Un secret TOTP n'est scellé de nouveau que quand la personne s'inscrit de nouveau, une clé de signature que quand elle est remplacée. | Faites tourner les clés de signature (`rotate-keys --now`, puis attendez `oidc.retired_key_retention`) et demandez aux personnes qui ont une application d'authentification de s'inscrire de nouveau ; d'ici là, ces lignes sont aussi exposées qu'avant la clé. |
| Le coût de RS256 | La bibliothèque reconstruit le PEM d'une clé RSA à chaque signature : quelques millisecondes par signature RS256, contre bien moins d'une milliseconde pour ES256 ou EdDSA (`tests/Benchmark/JwsBench.php`). Un déluge de requêtes de jeton coûte plus de temps de processeur avec RS256. | Les limites de débit du pare-feu sur le point de terminaison de jeton ; `oidc.algorithm: ES256` ou `EdDSA` quand les clients les prennent en charge. |
| `web-token/jwt-library` elle-même | Une faille de la bibliothèque est une faille du fournisseur. | Une seule bibliothèque maintenue ; sa version suit les avis de sécurité (`composer deps:audit`) ; l'entrée non fiable est bornée avant elle. |
| Un code présenté par deux requêtes à la fois | La requête qui perd le code révoque sa famille comme une réutilisation ; quand elle le fait avant que la gagnante ait enregistré ses jetons, ces jetons restent valides. | Rare : deux échanges d'un même code à quelques millisecondes d'écart ; borné par `oidc.access_token_lifetime` et, pour un jeton de rafraîchissement, par sa rotation ou sa révocation suivante ; la réutilisation est journalisée comme `identity.oidc.token_reuse`. |
| Des jetons de rafraîchissement qui survivent à la session | Avec `oidc.refresh_token_bound_to_session: false`, ou sans le module `sessions`, un jeton de rafraîchissement dure `oidc.refresh_token_lifetime` quoi qu'il advienne de sa session ; un jeton émis sans session le fait toujours. | Activé par défaut : la fin de la session refuse ses jetons de rafraîchissement et révoque leur famille ; `oidc.session_max_age` les borne après l'ouverture de la session. |
| La gagnante de deux actualisations simultanées perd ses jetons | La seconde de deux actualisations d'un même jeton est refusée comme une réutilisation et révoque la famille, les nouveaux jetons de la gagnante compris : un client qui s'actualise deux fois à la fois déconnecte sa personne. | Le côté sûr de la course : un jeton volé ne peut pas gagner en silence. Un client s'actualise une fois à la fois. |
| Des assertions de client sur plusieurs serveurs | Un magasin d'assertions utilisées local à chaque serveur accepterait une assertion une fois par serveur. | `phil_identity_assertion` vit dans la base de données du fournisseur, partagée comme les jetons. |
| Une nouvelle adresse posée avant d'être prouvée | Un changement d'adresse depuis la page du compte prend effet aussitôt, non vérifiée, et l'ancienne adresse n'en est pas avertie. | Le changement exige une preuve récente de la méthode la plus forte (`PHIL_STEP_UP`) ; la nouvelle adresse reste non vérifiée jusqu'à l'usage de son lien. Un avis à l'ancienne adresse et un changement retenu jusqu'à la preuve de la nouvelle sont prévus. |
| Reconnaître une adresse connue au temps de l'inscription | Une inscription pour une adresse connue répond sans hacher de mot de passe, donc plus tôt. | Les limites d'inscription par client et par adresse bornent les essais ; la réponse et son statut sont les mêmes. |
| La certification | La suite s'exécute en local ; la certification par l'OpenID Foundation exige un fournisseur à une adresse publique. | Faite avant l'utilisation du fournisseur en production ; voir [Conformité OpenID](openid-conformance.md). |

## La revue avant la production

Le réviseur suit le [guide du réviseur](security-review.md) : le code de `Application\Jose`, `Domain\WebAuthn`,
`Domain\Oidc`, `Application\Oidc`, `Infrastructure\Security` et `Infrastructure\Http\Oidc` à partir de cette page, les
tests, les cibles de fuzzing et la [suite de conformité OpenID](openid-conformance.md), et il dépose en privé ce qu'il
trouve, comme l'explique la [politique de sécurité](../../SECURITY.md).
