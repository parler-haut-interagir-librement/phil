<!-- phil-dev source-sha256=c3c5457643f91f3b39949d7c666dd20a8111a8c6653885c8c966e0838d13bbe2 -->
# Services

## `MessengerType`

Un service est un cas d'une enum qui implémente `MessengerType` :

| Méthode | Donne |
|---|---|
| `normalize(string $input): ?string` | L'identifiant canonique que représente l'entrée, ou `null` quand elle n'en est pas un |
| `display(string $handle): string` | L'identifiant tel que les gens le lisent |
| `link(string $handle): ?string` | L'adresse qui ouvre une conversation, ou `null` |

## Les services internationaux

| Service | Identifiant | Lu à partir de | Lien |
|---|---|---|---|
| `WhatsApp` | Un numéro de téléphone, `+15145550123` | `wa.me/…`, `api.whatsapp.com/send?phone=…` | `https://wa.me/15145550123` |
| `Telegram` | Un nom d'utilisateur de 5 à 32 lettres, chiffres et `_`, en minuscules | `@name`, `t.me/…`, `telegram.me/…` ; pas les pages propres à Telegram, `t.me/joinchat/…`, `t.me/addstickers/…` et autres | `https://t.me/name`, affiché `@name` |
| `Signal` | Un numéro de téléphone, ou un nom d'utilisateur `name.01` | `signal.me/#p/…` | `https://signal.me/#p/+1…` pour un numéro ; aucun pour un nom d'utilisateur |
| `Messenger` | Un nom d'utilisateur Facebook | `m.me/…` | `https://m.me/name` |
| `Matrix` | `@user:server` | `matrix.to/#/…` | `https://matrix.to/#/@user:server` |
| `Xmpp` | `user@server`, sans `?`, `#` ni caractère de contrôle | `xmpp:…` | `xmpp:user@server`, l'utilisateur encodé en pourcentage comme l'écrit la RFC 5122 |
| `Threema` | Un identifiant de 8 caractères, en majuscules | `threema.id/…` | `https://threema.id/ID` |
| `Viber` | Un numéro de téléphone | — | `viber://chat?number=…` |
| `Line` | Un identifiant de 4 à 20 caractères ; celui d'un compte officiel garde son `@` | `~id`, `@id`, `line.me/ti/p/~…`, `line.me/R/ti/p/~…` et `…/@…` ; pas un code d'ami, `line.me/ti/p/AbCd…` | `https://line.me/ti/p/~id` ; `https://line.me/R/ti/p/@id` pour un compte officiel |
| `Discord` | Un nom d'utilisateur de 2 à 32 caractères | `@name` | aucun, affiché `@name` |
| `WeChat` | Un identifiant de 6 à 20 caractères, commençant par une lettre | — | aucun |
| `IMessage` | Un numéro de téléphone, ou une adresse courriel en minuscules | — | aucun |
| `Snapchat` | Un nom d'utilisateur de 3 à 15 lettres, chiffres, `.`, `-` et `_`, commençant par une lettre et finissant par une lettre ou un chiffre | `@name`, `snapchat.com/add/…` | `https://www.snapchat.com/add/name` |
| `Session` | Un Session ID : `05` suivi de 64 chiffres hexadécimaux, en minuscules | — | aucun |

**Un numéro de téléphone s'écrit au format international**, avec `+` et l'indicatif du pays ; les espaces, les points,
les tirets et les parenthèses sont tolérés. `phil/phone` le lit : le numéro doit exister dans le plan de numérotation de
son pays et ne porter aucun poste, et il est gardé en E.164, `+15145550123`, et affiché au format international,
`+1 514-555-0123`. `label()` donne le nom propre du service, `XMPP`, `iMessage`.

**iMessage n'a pas de lien** : `sms:` ouvre les messages texte de l'appareil, iMessage ou non, et Apple ne publie aucune
adresse qui lui soit propre.

## Les services présents

Un service figure dans `KnownMessenger` quand il est utilisé dans plusieurs pays et qu'il nomme lui-même ses
utilisateurs, ou par un numéro de téléphone ou une adresse courriel. Sont exclus :

| Genre | Par exemple | Raison |
|---|---|---|
| Lié à un espace de travail ou à un serveur | Slack, Mattermost, Rocket.Chat, Microsoft Teams | Un nom ne veut rien dire sans son espace de travail |
| Un simple numéro de téléphone ou un compte | SMS, RCS, Google Chat | Un champ de téléphone ou de courriel, pas un service |
| Un réseau social | Instagram, X, Bluesky, Mastodon | Un profil, un genre d'URL plutôt qu'un identifiant |
| Régional | KakaoTalk, Zalo, QQ | L'enum propre à l'application, à côté de `KnownMessenger` |
| Sans identifiant, ou fermé | SimpleX, Skype | Rien à stocker |

## Les services propres à une application

Un forum communautaire, un réseau régional : une enum de l'application, qui implémente `MessengerType`, lue et affichée
à sa façon. Les champs et les contraintes l'acceptent à côté de `KnownMessenger`.
