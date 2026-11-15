<!-- phil-dev source-sha256=50992e2cd3f8491543b2ce55047bf794dd1666955e5a0f6bfdf75f00cd32478e -->
# Modèle de menaces

Ce dont le bundle se défend, comment, et ce qu'il laisse à l'application. Une revue de sécurité par quelqu'un qui n'a pas
écrit le code précède l'utilisation du bundle en production, à partir de cette page et du
[guide du réviseur](security-review.md).

## Actifs

| Actif | Où il se trouve | Ce qui le protège |
|---|---|---|
| Empreintes de mots de passe | `phil_identity_account` | Argon2id ; jamais journalisées, jamais dans un événement. |
| Clés publiques et compteurs des clés d'accès | `phil_identity_passkey` | Publiques par nature ; le compteur doit avancer. |
| Secrets TOTP, empreintes des codes de récupération | `phil_identity_totp`, `phil_identity_recovery_codes` | Scellés par le `SecretCipher` (composant KeyManagement) quand une clé est configurée ; codes calculés avec `secret`. |
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
| Deviner des mots de passe en ligne | Le `login_throttling` du pare-feu par adresse et par réseau ; le verrou progressif par compte, vérifié avant le mot de passe ; les clés d'accès connectent quand même un compte verrouillé. |
| Mots de passe faibles ou divulgués | `phil/passwords` : estimateur de force avec les données propres au compte comme premières hypothèses ; Pwned Passwords par k-anonymat quand il est activé. |
| Base de données de mots de passe volée | Argon2id ; aucun mot de passe dans un journal, un message ou un événement. |
| Hameçonnage | Clés d'accès : le navigateur ne signe que pour la partie de confiance ; origines vérifiées. |
| Code TOTP rejoué | Le compteur accepté est conservé ; un code ne se vérifie qu'une fois. |
| Authentificateur cloné | Le compteur de signature doit avancer quand l'authentificateur en tient un. |
| Réponses d'authentificateur malformées | Lecteur CBOR borné : profondeur, éléments et longueurs vérifiés avant l'allocation ; soumis au fuzzing. |
| Énumération de comptes | L'inscription, la réinitialisation du mot de passe et les liens de connexion répondent la même chose pour les adresses connues et inconnues. |
| Liens réutilisés | Liens signés sur les propriétés qu'ils changent — l'adresse et sa vérification, l'empreinte du mot de passe et sa date —, de sorte que chacun ne fonctionne qu'une fois sans rien stocker. |

## Attaques sur les sessions

| Menace | Défense |
|---|---|
| Fixation de session | Symfony Security migre la session à la connexion. |
| Un appareil volé | Les sessions fermées depuis un autre appareil prennent effet à la requête suivante ; les jetons émis dedans sont révoqués. |
| Une session ouverte depuis longtemps utilisée pour une action sensible | `PHIL_STEP_UP` demande une preuve récente de la méthode la plus forte. |
| Un deuxième facteur contourné | La deuxième étape est imposée à chaque requête jusqu'à ce qu'une preuve forte soit sur le jeton. |
| Falsification de requête inter-sites | Chaque formulaire et chaque requête de script porte un jeton CSRF ; la déconnexion depuis un lien demande de confirmer. |

## Attaques sur le fournisseur

| Menace | Défense |
|---|---|
| Redirection ouverte, vol de code par une adresse de redirection | Correspondance exacte avec une adresse enregistrée, lue par `phil/url` ; erreurs sur le client montrées, non redirigées. |
| Interception ou injection de code | PKCE S256 exigé de chaque client public et, par défaut, de chaque client confidentiel ; un code émis sans défi refuse un vérificateur au point de terminaison de jeton (RFC 9700, section 2.1.1) ; codes hachés, de courte durée, utilisés une fois, la réutilisation révoquant la famille. |
| Vol de jeton d'actualisation | Rotation avec détection de réutilisation qui révoque la famille. |
| Falsification de jeton | Signatures asymétriques seulement — RS256, ES256, EdDSA. Avant la bibliothèque : un jeton d'au plus `Jws::MAX_LENGTH` octets, trois parties, un en-tête et des revendications qui sont des objets JSON de profondeur bornée, un en-tête qui nomme un algorithme et un `kid`, ni `crit` ni `b64`. Ensuite le `kid` doit nommer une clé publiée, l'algorithme de l'en-tête doit être l'`alg` propre à la clé, et la bibliothèque vérifie avec cet algorithme seul : `none`, HMAC et la confusion d'algorithmes sont refusés. |
| Clés trop faibles ou d'un mauvais genre | Nouvelles clés : RSA de 3072 bits, P-256, Ed25519, créées par `JWKFactory`. Une clé relue du magasin doit correspondre au type et à la courbe de son algorithme et contenir tous ses membres privés ; un module RSA de moins de 2048 bits est refusé. |
| Objets de requête et assertions de client | Non acceptés : `request` et `request_uri` sont refusés avec `request_not_supported` et `request_uri_not_supported` avant que quoi que ce soit les lise ; les clients s'authentifient par secret ou, publics, par PKCE. Aucun JSON Web Token d'un client n'atteint la bibliothèque. |
| Confusion entre fournisseurs | L'émetteur dans la réponse d'autorisation (RFC 9207). |
| Compromission de clé | Rotation avec publication avant l'emploi ; `rotate-keys --now` ; clés privées scellées par le composant KeyManagement quand il est configuré. |
| Un client qui lit les jetons d'un autre | L'introspection et la révocation ne répondent que pour les jetons du client qui demande. |

## Liaison de comptes

Une identité externe ne fusionne jamais dans un compte sur une adresse que son fournisseur n'a pas vérifiée ; sur une
adresse vérifiée, seulement quand l'option est activée et que le compte l'a vérifiée aussi. Délier conserve une façon de
se connecter.

## Vie privée

Le bundle ne garde aucune adresse de connexion, seulement un nom d'appareil grossier, aucun jeton d'un fournisseur
fédéré. Ses événements et ses lignes d'audit portent des identifiants, jamais une adresse, un nom, un code ou un secret.

## Ce que l'application garde

Les limites de débit du pare-feu, la sécurité du transport (HTTPS, HSTS, témoins sécurisés), les en-têtes de sécurité,
le coffre à secrets qui détient `secret`, les contrôles d'accès de la base de données, la politique de sauvegarde et
d'effacement, et la revue de ses propres gabarits quand elle remplace ceux du bundle.

## Risques ouverts

| Risque | Pourquoi il reste | Ce qui le réduit |
|---|---|---|
| Un client confidentiel enregistré avec `--pkce-optional` | Le profil Basic d'OpenID Connect permet à un client confidentiel de se passer de PKCE, et la suite de conformité joue un tel client. | Désactivé par défaut ; un tel client envoie un `nonce`, que le jeton d'identité rapporte ; un client public ne peut jamais s'en passer. |
| Un magasin de clés sans le composant KeyManagement | Les JSON Web Keys privées sont alors gardées en clair dans la base de données. | Configurez `secrets.kms_key` ; restreignez l'accès à `phil_identity_signing_key`. |
| Le coût de RS256 | La bibliothèque reconstruit le PEM d'une clé RSA à chaque signature : quelques millisecondes par signature RS256, contre bien moins d'une milliseconde pour ES256 ou EdDSA (`tests/Benchmark/JwsBench.php`). Un déluge de requêtes de jeton coûte plus de temps de processeur avec RS256. | Les limites de débit du pare-feu sur le point de terminaison de jeton ; `oidc.algorithm: ES256` ou `EdDSA` quand les clients les prennent en charge. |
| `web-token/jwt-library` elle-même | Une faille de la bibliothèque est une faille du fournisseur. | Une seule bibliothèque maintenue ; sa version suit les avis de sécurité (`composer deps:audit`) ; l'entrée non fiable est bornée avant elle. |
| La certification | La suite s'exécute en local ; la certification par l'OpenID Foundation exige un fournisseur à une adresse publique. | Faite avant l'utilisation du fournisseur en production ; voir [Conformité OpenID](openid-conformance.md). |

## La revue avant la production

Le réviseur suit le [guide du réviseur](security-review.md) : le code de `Application\Jose`, `Domain\WebAuthn`,
`Domain\Oidc`, `Application\Oidc`, `Infrastructure\Security` et `Infrastructure\Http\Oidc` à partir de cette page, les
tests, les cibles de fuzzing et la [suite de conformité OpenID](openid-conformance.md), et il dépose en privé ce qu'il
trouve, comme l'explique la [politique de sécurité](../../SECURITY.md).
