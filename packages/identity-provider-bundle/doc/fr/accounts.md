<!-- phil-dev source-sha256=ef498fe9f61f6603da9486704097ab0ccc38a89396a7be946792a5bd4601aa03 -->
# Comptes et mots de passe

## Comptes

Un compte, une ligne de `phil_identity_account`, contient son identifiant, son adresse de connexion en minuscules, la
forme canonique de cette adresse et le moment où elle a été vérifiée, le nom qu'il affiche, l'empreinte Argon2id de son
mot de passe — aucune pour un compte qui se connecte seulement avec des clés d'accès ou un fournisseur fédéré —, un
numéro de téléphone et le moment où il a été vérifié, ses rôles, les échecs de connexion de suite et le verrou auquel
ils mènent, et son état et le moment où il a changé pour la dernière fois. Un profil plus étoffé relève de
l'application.

Symfony Security le voit comme `AccountUser`, identifié par l'identifiant du compte — jamais par son adresse, qui peut
changer et ne doit pas voyager dans des liens. `AccountUserProvider` le charge par cet identifiant ou par l'adresse
saisie dans un formulaire de connexion, distingués par le `@` qu'une adresse a et qu'un identifiant n'a jamais, et
réécrit une empreinte que le hacheur de mots de passe demande de renforcer. Deux
utilisateurs sont les mêmes tant que leur identifiant, leurs rôles, leur état et l'empreinte de leur mot de passe le
sont : une session ouverte avant que le mot de passe change ou soit retiré est déconnectée à sa requête suivante. La
session où le changement est fait porte la nouvelle empreinte et reste ouverte ; les lignes des autres sessions, et les
jetons émis dans celles-ci, sont fermés aussi (voir [Appareils et sessions](sessions.md)).

`AccountUserChecker` refuse un compte qui n'est pas actif, par n'importe quelle méthode, avec `account.disabled` et, avec
`require_verified_email`, un compte dont l'adresse n'est pas vérifiée, avec `account.unverified`.

### Identifiants

L'identifiant d'un nouveau compte vient du port `Application\Contract\AccountIdGenerator`, que nomme
`accounts.id_generator` :

| Valeur | Identifiant |
|---|---|
| `ulid`, par défaut | Un ULID de 26 caractères en base32 de Crockford (`UlidAccountIdGenerator`) |
| `uuid_v7` | Un UUID de version 7 en ses 36 caractères (`UuidV7AccountIdGenerator`), ordonné par le temps, comme les clés d'une application qui emploie ailleurs UUIDv7 |
| Un id de service | Un service qui implémente `AccountIdGenerator` |

Un identifiant contient de 1 à 36 lettres, chiffres et traits d'union (`Account::ID_PATTERN`) et n'est jamais donné deux
fois ; chaque table s'y réfère dans une colonne de 36 caractères. Il reste à l'intérieur du fournisseur : ce que les
clients voient comme `sub` est celui de la stratégie de sujet, `oidc.subject` (voir [Jetons](oidc-provider.md#jetons)).
Un UUIDv7 ou un ULID dit quand le compte a été créé ; avec l'un ou l'autre, donnez aux clients un sujet opaque —
`public` ou `pairwise` —, aléatoire pour eux et distinct de l'identifiant, plutôt que `account`.

### États

`Domain\Account\AccountState` nomme l'état d'un compte, assez générique pour n'importe quelle application ; seul un
compte actif se connecte :

| État | Sens |
|---|---|
| `active` | Le compte se connecte. |
| `pending` | Le compte attend une décision de l'application, comme une approbation. |
| `suspended` | Le compte est mis de côté pour un temps, par un modérateur ou après un doute, et peut revenir. |
| `closed` | Le compte est fermé, par son propriétaire ou par l'application, et attend son retrait. |

`Accounts::changeState()` fait passer un compte à un état, journalise `identity.account.state_changed` avec `from` et
`to` et, quand le compte quitte `active`, ferme chaque session du compte et les jetons émis dans celles-ci.
`Account::state()` et `stateChangedAt()` le lisent ; `isDisabled()` est vrai dans tout état sauf `active`, `disable()`
suspend un compte actif et `enable()` le rend de nouveau actif.

### Adresses

L'adresse de connexion a une forme canonique, `email_canonical`, unique parmi les comptes : deux adresses d'une même
forme canonique mènent à une même boîte de courriel, donc un seul compte suffit. L'inscription avec une autre
graphie d'une boîte connue répond comme pour l'adresse connue, et un changement d'adresse vers elle est refusé avec
`email.taken`. Le port `Application\Contract\AddressCanonicalizer` donne la forme ; l'`EmailCanonicalizer` du bundle
garde l'adresse telle que normalisée et, avec `accounts.strip_subaddress: true`, omet le sous-adressage —
`ana+news@example.com` devient `ana@example.com`. Les règles d'un fournisseur de courriel, comme les points que certains
ignorent, relèvent d'un service de l'application nommé dans `accounts.canonicalizer`. La forme d'une adresse conservée
ne change jamais d'elle-même : une nouvelle règle exige que les formes conservées soient recalculées.

Un compte peut aussi avoir une adresse pour chacun de deux autres usages, `Domain\Account\AddressPurpose` :
`recovery`, pour récupérer le compte, et `notices`, pour recevoir ses avis. Ce sont des lignes d'une seule table,
`phil_identity_address`, chacune avec sa forme canonique, qui appartient à un seul compte pour chaque usage.
`AccountAddresses` les gère :

| Méthode | Ce qu'elle fait |
|---|---|
| `set($account, $purpose, $address)` | Pose l'adresse de l'usage, non vérifiée ; refuse `sign_in` (`address.sign_in`), une adresse mal formée (`email.invalid`) ou une adresse qu'un autre compte détient pour l'usage (`email.taken`). |
| `verify($account, $purpose)` | La marque vérifiée, après un lien que l'application y a envoyé par exemple. |
| `addressFor($account, $purpose)` | Où écrire pour l'usage : son adresse vérifiée, sinon l'adresse de connexion. |
| `of($account)`, `get($account, $purpose)`, `remove($account, $purpose)` | Les lisent et les retirent. |

Les événements `identity.address.set`, `identity.address.verified` et `identity.address.removed` portent l'usage,
jamais l'adresse. Les courriels du bundle vont à l'adresse de connexion ; un `AccountMailer` de l'application peut
écrire à `addressFor()`.

## Inscription

`/register` prend l'adresse, un nom, et le mot de passe quand le module `password` est activé. Une adresse qui a déjà
un compte ne crée rien et la page répond la même chose — « vérifiez votre courriel » —, de sorte que le formulaire ne
dit à personne qui a un compte ; l'événement `identity.account.registration_for_known_email` permet à l'application
d'avertir le titulaire. Un nouveau compte reçoit un lien qui vérifie son adresse.

### Limiter les inscriptions

Chaque inscription coûte un hachage de mot de passe, une ligne et un courriel : l'inscription est donc limitée par
défaut, par le composant Rate Limiter :

```yaml
phil_identity_provider:
    registration:
        rate_limit:
            per_ip: { limit: 10, interval: '1 hour' }
            per_address: { limit: 5, interval: '1 hour' }
            resend_delay: '5 minutes'
```

`RegistrationRateLimiter` compte les inscriptions par adresse de client — un client IPv6 par son réseau /64 — et par
adresse saisie, connue ou non, de sorte que la réponse ne dit toujours à personne qui a un compte ; un refus répond
`429` avec le formulaire et `error.registration.too_many`. Un courriel de vérification va à une adresse une fois par
`resend_delay`, qu'il soit demandé par l'inscription, par la page du compte ou par un changement d'adresse : un courriel
redemandé trop tôt n'est pas envoyé, et la page répond comme s'il l'était. Les clés sont des empreintes à clé, sous une clé dérivée de `secret` par HKDF-SHA256 pour ce limiteur seul : la
cache ne contient jamais une adresse.

Les comptes vivent dans `cache.app`, sous `lock.factory` quand il est défini. Sur plusieurs serveurs, pointez `cache.app`
vers un stockage partagé, sans quoi chaque serveur compte pour lui seul. `registration: { rate_limit: false }` désactive
les limites, pour une application qui limite elle-même la route ; `registration: false` ferme l'inscription.

## Adresses vérifiées

Chaque lien envoyé par courriel mène à `public_origin` : voir [Où mènent les liens](#où-mènent-les-liens).

Le lien de `/verify-email` porte l'identifiant du compte, une expiration et une signature calculée par le
`SignatureHasher` de Symfony Security, celui qu'utilisent ses liens de connexion, sur l'adresse et la date de sa
vérification, sous une clé dérivée de `secret` pour cet usage seulement. L'ouvrir affiche une page qui demande de
confirmer ; l'adresse est vérifiée par le formulaire que cette page envoie, avec son jeton CSRF. Les analyseurs des
services de courriel qui ouvrent les liens d'un message ne vérifient donc rien. Le lien cesse de fonctionner une fois
servi, une fois l'adresse changée, ou après `links.verify_lifetime` secondes, sans rien stocker — ou, dans le [mode
conservé](#liens-conservés), une fois son jeton servi ou révoqué. Une adresse changée n'est pas vérifiée tant que son
propre lien n'a pas servi.

## Liens conservés

Par défaut, les liens sont signés et rien n'est conservé : un lien fonctionne jusqu'à son expiration ou jusqu'à ce que
les propriétés sur lesquelles il a été signé changent, et personne ne peut en révoquer un encore en circulation.
`links.mode: stored` conserve plutôt un jeton pour chaque lien :

```yaml
phil_identity_provider:
    links: { mode: stored }
```

Le lien porte alors un jeton aléatoire de 32 octets à la place de la signature ; `phil_identity_link_token` en garde
l'empreinte à clé — un HMAC sous une clé dérivée de `secret` —, le compte, l'usage, l'expiration, et la signature des
propriétés comme empreinte. Un lien est valide tant que sa ligne existe, nomme le compte et l'usage, n'a pas expiré, et
que le compte a encore les propriétés sur lesquelles il a été signé. Vérifier une adresse sert le jeton, une seule fois
même quand deux requêtes l'envoient au même moment ; une réinitialisation de mot de passe révoque chaque lien de
réinitialisation du compte. Une copie de la table n'ouvre aucun compte.

`AccountLinks::revoke($account, $purpose)` révoque les liens d'un compte encore en circulation — d'un usage, ou de tous
—, et journalise `identity.links.revoked` avec leur nombre, comme après un doute sur la boîte de courriel.
`phil:identity-provider:purge` retire les jetons expirés. Les liens de connexion du module `login_link` sont les liens
de connexion de Symfony Security : donnez au `login_link` du pare-feu son `max_uses` et un `used_link_cache` partagé
pour qu'ils ne fonctionnent qu'une fois.

## Mots de passe

Un nouveau mot de passe doit compter de 8 à 4096 caractères — la borne empêche un calcul d'empreinte de devenir un déni
de service —, atteindre `password.min_score` sur l'estimateur de force de `phil/passwords`, l'adresse et le nom du
compte comptant parmi les premières hypothèses de l'attaquant, et, avec `password.breach_check`, être absent de Pwned
Passwords, interrogé par k-anonymat. Une source de fuites qui ne répond pas ne fait pas refuser le mot de passe. Les
raisons reviennent sous forme de clés de traduction : `password.too_short`, `password.too_long`, `password.too_weak`,
`password.breached`.

Les mots de passe sont hachés par le hacheur de mots de passe de Symfony Security : le bundle préconfigure `sodium` —
Argon2id — pour `AccountUser`, et l'application peut l'ajuster dans `security.password_hashers`.

### Le plafond des vérifications de mot de passe

Chaque empreinte Argon2id prend des dizaines de mébioctets. Une rafale de connexions échouées réparties sur beaucoup de
comptes et beaucoup d'adresses passe sous les limiteurs par adresse et par compte, mais un serveur qui calculerait
chaque empreinte en même temps manquerait de mémoire. Le bundle décore la fabrique de hacheurs de mots de passe de
Symfony Security : chaque empreinte et chaque vérification du mot de passe d'un compte — connexion, changement,
réinitialisation, inscription — prend d'abord l'un des `password.semaphore.slots` verrous du composant Lock, et attend
jusqu'à `password.semaphore.wait` secondes qu'un se libère. Passé cette attente, la connexion est refusée avec
`password.busy`, qui ne compte jamais vers le verrou du compte. Savoir si une empreinte doit être recalculée ne prend
aucune place. Les hacheurs des autres classes d'utilisateurs sont laissés tels quels.

Le plafond appartient à chaque serveur, puisque la mémoire aussi : la valeur par défaut garde des fichiers flock dans le
répertoire temporaire, et un verrou meurt avec le processus qui le tenait. `password.semaphore.lock_factory` nomme une
autre `LockFactory`, par exemple une sur `SemaphoreStore` ; un magasin partagé par tous les serveurs plafonnerait tout le
site au nombre de places.

```yaml
phil_identity_provider:
    password:
        semaphore: { slots: 8, wait: 2.0 }
```

## Le mot de passe oublié

`/forgot-password` envoie un lien à l'adresse quand elle a un compte, et répond la même chose quand elle n'en a pas. Le
lien de `/reset-password` est signé sur l'empreinte du mot de passe et la date de son dernier changement : il cesse de
fonctionner dès que le mot de passe change, ou après `links.reset_lifetime` secondes. Le suivre et choisir un mot de
passe vérifie aussi l'adresse, ce que le lien a prouvé, et ferme toutes les sessions du compte.

Le lien sert une fois même quand deux requêtes le présentent en même temps : `Accounts::resetPassword()` pose le mot de
passe par `AccountRepository::apply()`, qui relit le compte sous un verrou de sa ligne, et seulement s'il a encore
l'empreinte et la date sur lesquelles le lien a été signé. La requête qui arrive en second voit le lien comme déjà
servi.

Un mot de passe changé depuis la page du compte ferme les autres sessions du compte et garde celle où il a été changé.

## Le verrou

`PasswordSignInListener` surveille les connexions par mot de passe de chaque authentificateur du pare-feu —
`form_login`, `json_login`, `http_basic`. Avant que le mot de passe soit vérifié, un compte verrouillé est refusé avec
`account.locked`, de sorte que les tentatives de devinette se poursuivent contre rien ; un mauvais mot de passe compte
vers le verrou ; une réussite remet le compte à zéro. Après `lockout.threshold` échecs de suite, chaque échec de plus
verrouille le compte deux fois plus longtemps que le précédent, de `lockout.base` secondes jusqu'à `lockout.max`. Le
verrou refuse seulement le mot de passe : une clé d'accès, qu'un attaquant qui devine ne peut pas rejouer, connecte
quand même. Chaque échec est compté sur le compte relu sous un verrou de sa ligne, de sorte que des échecs au même
moment comptent tous.

Le verrou laisse quiconque connaît une adresse en verrouiller le propriétaire en échouant exprès. `lockout: false` le
désactive : les échecs sont toujours comptés et journalisés comme `identity.sign_in.failed`, et rien n'est verrouillé.
Les essais sont alors laissés au [limiteur de connexion](#limiter-les-connexions), qui compte par adresse, par
identifiant et adresse, et par identifiant quelle que soit l'adresse, et qui refuse les tentatives plutôt que le
compte : activez `login_limiter` avec lui.

```yaml
phil_identity_provider:
    lockout: false
    login_limiter: { enabled: true }
```

## Limiter les connexions

Le `login_throttling` du pare-feu limite les tentatives devant chaque authentificateur. Son limiteur par défaut compte
par adresse et par identifiant et adresse ; une attaque répartie sur beaucoup d'adresses reste sous les deux. Avec
`login_limiter` activé, le bundle offre un limiteur qui compte aussi par identifiant, quelle que soit l'adresse :

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    login_limiter:
        enabled: true
        account_max_attempts: 50   # per identifier, whatever the address
        cache_pool: cache.redis    # shared by every server

# config/packages/security.yaml
security:
    firewalls:
        main:
            login_throttling:
                limiter: phil_identity_provider.login_limiter
```

`LoginRateLimiter` compte trois choses à chaque `login_limiter.interval` : les tentatives par adresse — un client IPv6
par son réseau /64 —, `network_max_attempts` ; par identifiant et adresse, `max_attempts` ; par identifiant, quelle que
soit l'adresse, `account_max_attempts`. Les clés sont des empreintes à clé de l'adresse et de l'identifiant en
minuscules, sous une clé dérivée de `secret` par HKDF-SHA256 pour ce limiteur seul : le cache ne garde ni l'une ni l'autre. Une connexion réussie efface les deux comptes de son identifiant et
rend son jeton à son adresse. Les options de `login_throttling` lui-même, `max_attempts` et `interval`, ne sont plus
lues dès qu'un limiteur est nommé.

Deux réglages comptent :

- Le compte par identifiant peut être dépensé par quiconque connaît une adresse : échouer exprès retient les connexions
  de ce compte pendant l'intervalle. Gardez `account_max_attempts` bien au-dessus de `max_attempts` ; les clés d'accès
  et les liens de connexion passent par le même pare-feu, et l'application qui veut les épargner nomme leurs
  authentificateurs dans un autre pare-feu ou un autre limiteur. Le verrou progressif, qui refuse seulement le mot de
  passe, reste par-dessus.
- L'adresse est celle que voit Symfony. Derrière un mandataire ou un répartiteur de charge, réglez
  `framework.trusted_proxies` et `framework.trusted_headers` sur les seuls mandataires : sinon tous les clients
  partagent l'adresse du mandataire et sa limite, ou, avec un en-tête cru de n'importe qui, un client choisit son
  adresse et y échappe.

Les comptes vivent dans `login_limiter.cache_pool`, `cache.rate_limiter` par défaut : sur plusieurs serveurs, une
réserve sur un stockage partagé — Redis, une base de données — compte pour tous, et `login_limiter.lock_factory`, sur
un stockage de verrous partagé, fait compter deux tentatives au même moment. Le bundle confie la réserve au
`RateLimitGuard` de `phil/rate-limit` : dans l'environnement `prod`, une réserve locale à chaque serveur — le système de
fichiers, APCu, un tableau — fait échouer la construction du conteneur, sauf si `login_limiter.single_server: true` dit
que l'application tourne sur un seul serveur.

## Où mènent les liens

Les liens des courriels — vérification, réinitialisation, connexion — sont des adresses absolues, et une requête nomme
son propre hôte dans son en-tête `Host`. Sans précaution, quelqu'un demande un lien de réinitialisation pour une autre
personne en nommant son propre hôte : la personne reçoit un vrai lien signé qui mène à cet hôte, et un clic livre le
jeton. La signature couvre le compte et l'expiration, non la destination du lien.

Le bundle construit donc ces liens sur `public_origin`, l'adresse canonique du site :

```yaml
phil_identity_provider:
    public_origin: '%env(APP_PUBLIC_ORIGIN)%'   # https://example.com
```

Le contexte du routeur nomme cette origine — schéma, hôte et port — pendant la génération du lien, et le contexte de la
requête revient aussitôt ; le chemin de base de l'application est conservé. `phil/url` lit l'origine au démarrage du
noyau : une adresse `http` ou `https` avec un nom d'hôte, sans adresse IP, sans chemin, sans requête ni fragment ;
`localhost` seulement en mode débogage. Une application qui sert un hôte par locataire remplace
`Application\Contract\LinkOrigin` par la sienne, qui connaît l'hôte du locataire.

Sans `public_origin`, les liens prennent l'hôte de la requête, que `framework.trusted_hosts` doit borner :

```yaml
framework:
    trusted_hosts: ['^example\.com$']
```

Une requête vers un autre hôte est alors refusée avec `400` avant qu'aucun lien soit fait. Dans l'environnement `prod`,
le bundle refuse de démarrer quand ni `public_origin` ni `framework.trusted_hosts` n'est réglé. Régler les deux est le
plus sûr : les hôtes de confiance refusent les requêtes falsifiées vers chaque page, l'origine fixe la destination des
courriels.

## La page du compte

`/account` montre le nom, l'adresse et si elle est vérifiée, les formulaires qui les changent ainsi que le mot de
passe, et des liens vers les pages des modules activés. Changer l'adresse ou le mot de passe exige une preuve récente de
la méthode la plus forte du compte : voir [Réauthentification](step-up.md).
