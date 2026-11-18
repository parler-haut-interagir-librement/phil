<!-- phil-dev source-sha256=c13bf2b306dbd71f970b750293e2b161be7023a486b7cbc99a1fc4c62dafab5f -->
# Invitations

Une invitation permet à quiconque détient son jeton d'adhérer à une organisation avec les rôles qu'elle porte : jusqu'à
son expiration, autant de fois qu'elle le permet, à moins qu'elle ne soit révoquée.

## En créer une

```php
$issued = $invitations->create($acme->id, $actor, ['member'], expiresAt: $clock->now()->modify('+3 days'), maxUses: 10);

$issued->token;          // 43 characters, shown once: put it in a link and send it
$issued->invitation;     // its identifier, roles, uses, moments and creator
```

L'acteur détient `members.invite` et n'accorde que des rôles dont il détient les permissions. Sans expiration,
l'invitation dure `invitations.default_lifetime` jours, et jamais plus que `max_lifetime` ; sans nombre d'utilisations,
elle en permet `default_uses`, et jamais plus que `max_uses`.

Le jeton est composé de 32 octets issus du générateur sécurisé de PHP, en Base64 sûr pour les URL, comme les jetons de
`phil/access-links`. La table ne conserve que son HMAC-SHA-256 calculé avec la clé de l'application : une copie de la
table n'ouvre rien. Le jeton va à son créateur une seule fois, et à aucun événement, journal ni table.

## En accepter une

```php
$invitation = $invitations->preview($token);                         // what it grants, without using it
$membership = $invitations->accept($token, MemberId::fromString($user->getId()));
```

`accept()` utilise l'invitation une fois et ajoute le membre avec ses rôles. Chaque utilisation est **un seul `UPDATE`
conditionnel** — ni révoquée, ni épuisée, ni expirée à cet instant — dont le nombre de lignes touchées est vérifié :
quand deux membres prennent en même temps sa dernière utilisation, l'un adhère et l'autre est refusé.

Une invitation est refusée par `InvitationNotUsable`, dont le message est le même quelle qu'en soit la cause, afin qu'il
ne dise rien d'un jeton ; sa `reason` indique à l'application laquelle :

| `reason` | Quand |
|---|---|
| `unknown` | Le jeton est mal formé, ou aucune invitation ne l'a |
| `expired` | Son expiration est passée |
| `revoked` | Elle a été révoquée |
| `used_up` | Elle a été utilisée autant de fois qu'elle le permet |
| `archived` | Son organisation est archivée |
| `creator` | Son créateur n'est plus un membre qui peut inviter et accorder ses rôles, ou a été effacé |

Un membre qui l'est déjà reçoit `AlreadyMember`, et l'invitation n'est pas utilisée.

## Révoquer et lister

```php
$invitations->revoke($acme->id, $invitation->id, $actor);    // members.invite
$invitations->invitations($acme->id);                         // the newest first
```

## Purger

`phil:organizations:purge-invitations` supprime les invitations expirées ou révoquées depuis plus de `purge_retention`
jours ; `Invitations::purge()` fait de même avec une durée de conservation en secondes. Exécutez-la chaque jour.

## Faire la rotation de la clé

`hash_key` accepte une liste : la première clé calcule l'empreinte des nouveaux jetons, et les autres retrouvent encore
les invitations créées sous elles. Ajoutez d'abord une nouvelle clé, puis retirez l'ancienne une fois que ses invitations
ont expiré :

```yaml
phil_organizations:
    invitations:
        hash_key: ['%env(ORGANIZATIONS_INVITATION_KEY)%', '%env(ORGANIZATIONS_INVITATION_KEY_PREVIOUS)%']
```

## Adhérer par un domaine vérifié

Une organisation peut consigner le domaine de courriel qu'elle a vérifié ; la vérification — un enregistrement DNS, un
message à une adresse du domaine — revient à l'application, avant l'appel :

```php
$organizations->useDomain($acme->id, $actor, 'acme.org');    // organization.manage; null removes it
```

Avec `domain_join.enabled`, désactivé par défaut, un membre dont l'application a vérifié l'adresse adhère à une
organisation dont le domaine vérifié est celui de l'adresse, avec les rôles de `domain_join.roles`, `member` par défaut
et jamais `owner` :

```php
$join->organizationsFor($verifiedAddress);                   // the active organizations of that domain
$join->join($acme->id, $member, $verifiedAddress);
```

Le domaine se compare exactement, en ASCII minuscule — un domaine internationalisé sous sa forme `xn--` — : `sub.acme.org`
n'est pas `acme.org`. L'adresse est lue pour son domaine et n'est jamais conservée, journalisée ni émise.
