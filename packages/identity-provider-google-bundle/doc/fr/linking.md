<!-- phil-dev source-sha256=3ff45d056e9ad4c31c75e9fff7a5febb680664f7ee730716c1105908827b4abe -->
# Liaison des comptes

Le paquet lit celui dont Google répond ; phil/identity-provider-bundle décide quoi en faire. Cette page dit ce que le
paquet remet, et comment les règles du bundle s'appliquent ensuite.

## La connexion

1. `/login/with/google` appelle `GoogleProvider::start()` : le paquet lit le point de terminaison d'autorisation de
   Google dans son document de découverte, fabrique un état, un *nonce* et un vérificateur PKCE de 256 bits chacun, les
   garde dans la session avec l'adresse de rappel, et envoie la personne chez Google avec l'état, le *nonce*, le défi
   PKCE (S256), les portées, `access_type=online` ainsi que le `prompt` et le `hd` configurés.
2. Google renvoie la personne à `/login/with/google/callback`, où l'`ExternalAuthenticator` du bundle appelle
   `GoogleProvider::finish()`. Le paquet prend la tentative de l'état — comparé avec `hash_equals()`, retiré quoi qu'il
   arrive —, refuse une réponse qui a attendu plus que `attempt_lifetime`, une erreur de Google, une réponse sans code,
   et un paramètre `iss` qui n'est pas celui de Google (RFC 9207).
3. Le code est échangé au point de terminaison des jetons de Google avec le vérificateur PKCE et le secret client. La
   signature du jeton d'identité est vérifiée contre les clés de Google, puis son émetteur — `https://accounts.google.com`,
   ou `accounts.google.com` comme Google l'écrit aussi —, son audience, son `azp`, ses heures et son *nonce*.
4. Les revendications sont transposées en l'identité que le bundle lie (voir plus bas), et un sujet retenu après un
   détournement est refusé.

Le jeton d'accès et le jeton d'identité sont lus puis abandonnés : rien n'en est conservé, et aucun jeton de
rafraîchissement n'est demandé. Chaque refus est le `external.failed` du bundle ; sa cause est journalisée au niveau
`info`, sans jeton ni donnée de la personne.

Plusieurs connexions peuvent attendre à la fois, une par onglet, cinq au plus : la plus ancienne est abandonnée quand
une sixième commence, pour qu'un visiteur ne puisse pas faire grossir la session.

## L'identité remise

| Champ | Provient de | Règle |
|---|---|---|
| `provider` | — | `google` |
| `subject` | `sub` | L'identifiant stable du compte chez Google, en ASCII imprimable de 1 à 255 octets ; tout autre est refusé. L'adresse n'est jamais la clé. |
| `email` | `email` | L'adresse quand c'en est une, de 254 octets au plus ; aucune sinon. |
| `emailVerified` | `email_verified`, `hd` | Vrai seulement quand Google est l'autorité de l'adresse : `email_verified` est vrai et l'adresse est une adresse `gmail.com`, ou le compte appartient à un domaine Google Workspace (`hd`) et l'adresse est sur ce domaine. |
| `displayName` | `name` | Seulement avec `profile: true` : caractères de contrôle retirés, espaces de bordure supprimées, 200 caractères au plus. |

Un compte Google peut porter une adresse de n'importe quel domaine — un compte grand public créé avec une adresse de
travail, par exemple —, et Google la marque vérifiée une fois que la personne a prouvé qu'elle en lit le courrier. Cela
prouve moins qu'un compte du domaine lui-même ; le paquet remet donc une telle adresse comme non vérifiée.

## Les règles du bundle

`ExternalIdentities` du bundle décide ensuite, dans l'ordre :

1. un compte Google déjà lié connecte son compte — et est refusé quand quelqu'un d'autre est connecté ;
2. une personne connectée lie le compte Google à son propre compte ;
3. un compte Google dont l'adresse est déjà celle d'un compte est refusé avec `external.email_known` : la personne se
   connecte à ce compte et y lie Google. Les comptes ne sont fusionnés sur l'adresse que si `external.link_verified_email`
   du bundle est activé, que le paquet a remis l'adresse comme vérifiée, et que le compte l'a vérifiée aussi ;
4. sinon, avec `external.register` du bundle, un compte sans mot de passe est créé et lié, son adresse vérifiée si le
   paquet l'a remise comme vérifiée.

## Lier et dissocier

Une personne connectée lie Google depuis la page `/account/identities` du bundle, dont le bouton « Link with Google »
démarre le même flux ; l'identité est alors liée à son compte. La même page la dissocie, après une preuve récente —
jamais la dernière façon de se connecter d'un compte sans mot de passe ni clé d'accès.

## Domaines acceptés

Avec `hosted_domains`, seuls les comptes Google Workspace de ces domaines se connectent : la revendication `hd`, que
Google ne pose que sur les comptes Workspace, doit être l'un d'eux. Le paramètre `hd` envoyé à Google ne fait que
restreindre son sélecteur de comptes ; une personne peut le retirer de l'adresse, et c'est la revendication qui est
vérifiée.

## Demander plus

Le paquet demande l'adresse seulement, et le nom avec `profile: true`. Une application qui a besoin de plus de données
Google d'une personne — son calendrier, ses contacts — les demande avec son propre écran de consentement et ses propres
jetons, au moment où elle en a besoin : ce paquet ne conserve aucun jeton avec lequel appeler les API de Google.
