<!-- phil-dev source-sha256=2d252985ce924bf0620fc66d1624a0d31f240f53b68b41d0ad0f23bee4817c51 -->
# Invitations

## Inviter

```php
$issued = $links->invite(
    'dinner-2026',                                // the campaign, as the application names it
    'member-1842',                                // the recipient's stable identifier
    new DateTimeImmutable('2026-12-01 12:00'),    // the closure: the answer stops changing
    new DateTimeImmutable('2026-12-15 12:00'),    // the expiry: the link stops opening, the closure by default
    new DateTimeImmutable('2026-11-20 09:00'),    // the opening: the link starts opening, now by default
);

$issued->invitation;   // Invitation
$issued->url;          // the link to send, a phil/url Url
$issued->token;        // the token alone, for a template that builds its own address
```

| Valeur | Bornes |
|---|---|
| Campagne | De 1 à 100 lettres, chiffres, deux-points, points, traits d'union et soulignés : `poll:42`, `dinner-2026` |
| Destinataire | De 1 à 191 octets, ne contenant jamais `@` : un identifiant de l'application, jamais une adresse courriel |
| Moments | Clôture après maintenant et après l'ouverture ; expiration à la clôture ou après |

Un destinataire est invité une seule fois par campagne : une seconde invitation lève `AlreadyInvited`, et un lien perdu
est émis de nouveau avec `reissue()` ; voir [Liens](links.md). L'`IssuedLink` refuse d'être sérialisé et cache son jeton
et son adresse à `var_dump()` : envoyez-le, puis laissez-le partir.

## Les moments

| Moment | Le lien | La réponse |
|---|---|---|
| Avant `validFrom` | N'ouvre rien | Ne peut pas être donnée |
| De `validFrom` jusqu'à `closesAt` | S'ouvre, modifiable | Donnée et modifiée |
| De `closesAt` jusqu'à `expiresAt` | S'ouvre, en lecture seule | Montrée, plus modifiée |
| À partir de `expiresAt` | N'ouvre rien | — |

Chaque moment vient de l'horloge et se compare par son ordre. `Invitation::opensAt($now)` et `isEditableAt($now)` disent
la même chose pour une invitation que détient l'application.

## Ouvrir

```php
$access = $links->open($request->query->getString('token'));

if (null === $access) {
    // malformed, unknown, revoked, not yet valid or expired: one page for all
}

$access->invitation->answer;   // the answer so far, or null
$access->editable;             // whether the form may be shown
```

Ouvrir ne change rien. Un client de courriel qui ouvre le lien pour en faire un aperçu, ou un analyseur de sécurité qui
le suit, ne dépense rien et ne répond à rien : seul `respond()` écrit.

## Répondre

```php
$invitation = $links->respond($token, 'yes, with one guest');
$invitation->respondedAt;   // the first answer
$invitation->answeredAt;    // the last change
```

La réponse fait de 1 à 4096 octets d'UTF-8, encodés comme l'application l'entend : un mot, un choix, un petit document
JSON. Répondre de nouveau la change jusqu'à la clôture. Un lien qui n'ouvre rien lève `LinkNotUsable`, dont le message
ne dit pas pourquoi ; une réponse après la clôture lève `InvitationClosed`. Une réponse arrête les rappels.

## Révoquer et compter

```php
$links->revoke($invitationId);    // false when revoked before; UnknownInvitation when unknown
$links->invitation($invitationId);
$links->counts('dinner-2026');    // CampaignCounts: invited, responded, revoked, pending()
```

Les liens d'une invitation révoquée n'ouvrent rien. Une invitation révoquée après avoir reçu une réponse compte comme
répondue.
