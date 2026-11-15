<!-- phil-dev source-sha256=b68b9f812fe725139ff7447165555d68728bc2356a0ff058a846d050727124c0 -->
# Application d'authentification et codes de récupération

## L'application d'authentification

Le module `totp` permet à un compte d'ajouter une application d'authentification, qui calcule des mots de passe à usage
unique fondés sur le temps (RFC 6238) : HMAC-SHA-1 du nombre de périodes de 30 secondes depuis l'époque, six chiffres.

`/account/two-factor`, après une preuve récente, génère un secret de 160 bits et montre son adresse `otpauth://` — le
lien qu'ouvre une application du même appareil, le contenu d'un code QR — ainsi que le secret en base32 pour le saisir à
la main. Le secret est conservé scellé par le `SecretCipher` : chiffré par le composant KeyManagement quand
`secrets.kms_key` est défini. Il ne compte pour rien tant qu'un premier code ne l'a pas confirmé. Un code est accepté
dans les `totp.window` périodes avant et après la période courante, et une seule fois : le compteur qu'il a trouvé est
conservé, et le même code, ou un plus ancien, ne se vérifie plus jamais. Chaque compteur de la fenêtre est calculé, de
sorte que le temps pris ne dit rien de celui qui a correspondu.

## Codes de récupération

Avec le module `recovery_codes`, confirmer l'application génère dix codes de dix caractères tirés d'un alphabet sans
caractères qui se ressemblent, affichés une seule fois sous la forme `XXXXX-XXXXX`. Ils sont conservés sous forme de
HMAC-SHA-256 sous une clé dérivée de `secret` ; un code est lu sans tenir compte de la casse, des espaces ni des
tirets, comparé en temps constant, et utilisé une seule fois. De nouveaux codes remplacent les anciens. Désactiver
l'application les retire.

## La deuxième étape

Quand un compte a une application d'authentification confirmée, `SecondFactorListener` envoie chaque requête à
`/two-factor` jusqu'à ce que le jeton détienne une preuve forte — `otp`, `hwk` ou `mfa` —, quelle qu'ait été la
première méthode : un mot de passe, un lien de connexion, un fournisseur fédéré. La page du défi offre un code de
l'application, un code de récupération et une clé d'accès du compte. `OneTimeCodeAuthenticator` prend le code,
authentifie de nouveau le même compte avec la preuve `otp`, que Symfony Security 8.2 ajoute aux preuves que le jeton
détient déjà, et envoie la personne là où elle allait. Les routes du défi, des clés d'accès et celles de `open_routes`
restent joignables, de sorte que la personne peut se déconnecter.

Le `login_throttling` du pare-feu s'applique à `OneTimeCodeAuthenticator` comme à n'importe quel authentificateur.
