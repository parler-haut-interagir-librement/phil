<!-- phil-dev source-sha256=53f27cb1b96e250c1785fc0b5336aa107967ad243ff34e94c39ea95fc6cf1b57 -->
# Liens

## Le jeton

Un jeton est fait de 32 octets du générateur sécurisé, soit 256 bits, en Base64 sûr pour les URL sans remplissage :
43 caractères de `A–Z a–z 0–9 - _`. Il ne nomme rien. `LinkToken::parse()` accepte exactement cette forme — 42
caractères, puis un dont les deux derniers bits sont nuls, comme les laisse l'encodage de 32 octets — et lève
`InvalidToken` pour tout le reste, sans répéter le texte.

## L'empreinte à clé

`TokenHasher` transforme un jeton en ce que conserve le magasin : le HMAC-SHA-256 du jeton sous la clé de l'application,
écrit `$hmac-sha256$v=1$` suivi de 64 chiffres hexadécimaux minuscules, soit 81 caractères. Une copie des tables
n'ouvre aucun lien sans la clé.

```php
use Phil\AccessLinks\TokenHasher;

$hasher = new TokenHasher($activeKey, $previousKey);
```

Les clés forment une liste ordonnée : la première calcule l'empreinte des nouveaux jetons, et chaque clé retrouve un
jeton. Pour faire tourner la clé, mettez la nouvelle en premier et gardez l'ancienne après elle ; les liens envoyés sous
l'ancienne clé continuent de s'ouvrir. Une fois que toutes les invitations liées sous une clé ont expiré, retirez cette
clé. Chaque clé fait au moins 32 octets, huit clés au plus, aucune donnée deux fois. Un jeton est recherché par son
empreinte sous chaque clé à la fois, par un index dont la durée ne dit rien d'un jeton puisque l'empreinte est à clé.

## L'adresse

`LinkFormat` construit le lien sur `phil/url` : l'adresse de la page et un paramètre de requête, `token` par défaut.

```php
use Phil\AccessLinks\LinkFormat;
use Phil\Url\Url;

$format = new LinkFormat(Url::fromString('https://events.example/rsvp?lang=fr'), 'token');
$format->for($token);   // https://events.example/rsvp?lang=fr&token=…
```

Le paramètre est composé de 1 à 32 lettres minuscules, chiffres et soulignés ; la page n'a pas de fragment ; seul un
jeton est porté. Servez la page avec `Referrer-Policy: no-referrer`, pour qu'un lien sortant de la page ne transmette pas
le jeton à un autre site, et avec `Cache-Control: private, no-store`.

## Les liens émis de nouveau

```php
$again = $links->reissue($invitationId);
```

Un destinataire qui a perdu son lien en reçoit un autre ; les liens précédents continuent de s'ouvrir. Chaque rappel en
émet un aussi, puisque le magasin ne peut pas rendre un jeton. Une invitation a seize liens au plus : le premier, ses
rappels et ceux émis à la main ; au-delà, `reissue()` lève `InvalidConfiguration`.

## Ce que le lien n'est pas

Le lien n'est pas une connexion. Il ouvre une invitation pour quiconque le détient, et la bibliothèque n'en fait pas une
session. Une application qui veut un lien de connexion signé pour ses membres emploie le lien de connexion de Symfony,
qui signe les propriétés de l'utilisateur et expire en quelques minutes ; un lien d'invitation dure jusqu'à son
expiration et se compte, se révoque et reçoit une réponse une invitation à la fois.
