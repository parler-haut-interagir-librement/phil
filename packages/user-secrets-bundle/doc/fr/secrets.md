<!-- phil-dev source-sha256=30b9da082873959f5166dad43fdc6d1988dd1b52ed03086f5daacfb27bf2e645 -->
# Stocker et lire les secrets

`UserSecrets` est le service du bundle. Chaque méthode nomme le propriétaire — la personne, par l'identifiant opaque
sous lequel l'application la connaît — et le nom du secret, unique parmi les secrets du propriétaire.

## Stocker

```php
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;

$owner = Owner::fromString($user->getUserIdentifier());

$secrets->store($owner, SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of($key));
$secrets->store($owner, SecretName::fromString('github'), ServiceName::fromString('github'), OAuthToken::of(
    $response['access_token'],
    $response['refresh_token'] ?? null,
    new DateTimeImmutable('+'.$response['expires_in'].' seconds'),
    explode(' ', $response['scope']),
));
```

Stocker sous un nom qui existe déjà remplace la valeur, son service et sa nature, et lève une révocation ; la date de
création reste. Le nom est au choix du propriétaire : `github.work` et `github.home` peuvent tous deux ouvrir `github`.

Un propriétaire compte de 1 à 255 octets, sans espace ni caractère de contrôle. Un nom et un service sont faits de
lettres minuscules, de chiffres, de `_`, de `.` et de `-`, commencent par une lettre et comptent 64 caractères au plus.

## Lister

```php
foreach ($secrets->secrets($owner) as $summary) {
    // $summary->name, ->service, ->kind, ->createdAt, ->updatedAt, ->lastUsedAt, ->expiresAt, ->revokedAt
}
```

Un `SecretSummary` contient tout sauf la valeur ; les secrets révoqués sont listés aussi.

## Lire par une tâche du propriétaire

Rien ne lit un secret sauf un `SecretAccess`, une tâche exécutée pour le propriétaire, qui nomme son but :

```php
$access = SecretAccess::forTaskOf($owner, 'nightly calendar sync');

$credential = $secrets->reveal($access, SecretName::fromString('openai'));   // Credential
$key = $secrets->apiKey($access, SecretName::fromString('openai'));          // ApiKey
$token = $secrets->accessToken($access, SecretName::fromString('github'));   // OAuthToken, refreshed if needed
```

L'accès lit les secrets de son propriétaire et de personne d'autre : la recherche se fait par son propriétaire, si bien
que le secret d'une autre personne n'est tout simplement pas trouvé. L'application construit l'accès là où elle sait
déjà pour qui la tâche s'exécute — la requête de la personne connectée, un *handler* dont le message nomme la
personne —, et jamais à partir d'une valeur que fournit la requête. Le but, de 1 à 255 octets, va à la piste d'audit
avec la lecture.

Une lecture date la dernière utilisation du secret. Les valeurs arrivent sous forme de `SecretValue`, qui s'affiche
comme `[redacted]` dans `var_dump()`, `print_r()` et les traces de pile, et refuse la sérialisation. Appelez
`reveal()` au moment où la valeur est envoyée au service.

## Révoquer, supprimer, effacer

```php
$secrets->revoke($owner, SecretName::fromString('openai'));   // value wiped, row kept with its date
$secrets->remove($owner, SecretName::fromString('openai'));   // row gone; false when there was none
$secrets->eraseOwner($owner);                                 // every secret of the owner gone; how many
```

Un secret révoqué ne peut pas être lu tant que le propriétaire n'a pas stocké une nouvelle valeur sous son nom.
`eraseOwner()` est ce qu'exécute le module de vie privée quand le compte est effacé, comme le montre
[Audit et vie privée](audit-and-privacy.md) ; il peut s'exécuter de nouveau sans dommage.

## Révocation auprès du service

Quand un jeton OAuth est révoqué, supprimé ou effacé, et qu'un `TokenRevoker` de l'application dessert son service, le
bundle demande aussi au service de le révoquer, une fois le jeton disparu localement : le premier révocateur qui prend
en charge le service reçoit le jeton, en général pour le point de terminaison de révocation du service
([RFC 7009](https://www.rfc-editor.org/rfc/rfc7009)). Un événement `revoked_at_service` suit. Un révocateur qui lève
une exception — le service est en panne, refuse, ou le révocateur a un bogue — ne ramène jamais le jeton et n'arrête
jamais un effacement : l'échec est écrit dans le service `logger` au niveau `warning`, avec son exception, et un
événement `service_revocation_failed` va aux auditeurs. Une valeur scellée qui ne s'ouvre plus est journalisée de la
même façon et effacée malgré tout.

Les clés d'API ne sont pas révoquées auprès du service : peu de services offrent un point de terminaison pour cela, et
le propriétaire révoque une clé là où il l'a créée. Un secret dont aucun révocateur ne dessert le service n'est pas
ouvert quand il part. [Jetons OAuth](oauth-tokens.md#écrire-un-révocateur) montre un révocateur.

## Écrans du propriétaire

Le bundle ne fournit ni formulaire, ni contrôleur, ni gabarit. Les écrans où un propriétaire colle une clé, connecte un
service par OAuth, liste ses secrets et en révoque ou en supprime un appartiennent à l'application, qui connaît ses
utilisateurs, ses routes, son apparence et la façon dont chaque service est connecté : une connexion OAuth est un
parcours de redirection du propre client de l'application. Chacun tient en quelques appels : `store()` depuis le
formulaire ou le rappel OAuth, `secrets()` pour la liste, `revoke()` et `remove()` derrière des boutons protégés contre
la CSRF. Montrez le résumé de chaque secret — son service, ses dates, s'il a été révoqué — et jamais sa valeur : le
bundle ne remet les valeurs qu'à un `SecretAccess`.
