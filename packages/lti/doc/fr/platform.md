<!-- phil-dev source-sha256=73f22913410da8facd5863c2f82f4dac4021133992c834df177886f95c382489 -->
# La plateforme

Une plateforme lance des outils pour ses membres. Elle enregistre chaque outil — son client, ses adresses, son jeu de
clés, les portées qu'il peut demander — et publie son propre jeu de clés et ses points de terminaison pour l'outil.

## Enregistrer un outil

```php
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Service\Scope;
use Phil\Url\Url;

$tools = new InMemoryToolRegistrations()->add(new ToolRegistration(
    clientId: 'client-42',
    loginUrl: Url::fromString('https://tool.example.com/lti/login'),
    redirectUrls: [Url::fromString('https://tool.example.com/lti/launch')],
    keySetUrl: Url::fromString('https://tool.example.com/lti/jwks'),
    scopes: [Scope::SCORE, Scope::LINE_ITEM, Scope::MEMBERSHIP_READ_ONLY],
    deepLinkingUrl: Url::fromString('https://tool.example.com/lti/launch'),
    name: 'The quiz tool',
), deploymentIds: ['1']);
```

L'identifiant du client revient à la plateforme : aléatoire, jamais réutilisé. Les adresses de redirection sont
comparées en entier, comme l'exige OpenID Connect.

## Démarrer un lancement

```php
use Phil\Lti\Platform\LaunchStarter;

$url = new LaunchStarter('https://courses.example.com', $tools)
    ->loginUrl($tool, deploymentId: '1', loginHint: $hintOfTheMember, targetLinkUri: $target, messageHint: $resourceLinkId);
```

Le navigateur va à cette adresse — dans un cadre ou une fenêtre — avec l'émetteur, l'indice de connexion, la cible, le
client, le déploiement et l'indice de message. L'indice de connexion est ce par quoi la plateforme reconnaît le membre
quand le navigateur revient : un identifiant opaque du compte, jamais son courriel. L'indice de message est ce par quoi
elle reconnaît le lancement — un lien de ressource, un placement de Deep Linking — ; l'outil le renvoie sans le lire. La
cible doit se trouver sur une origine de l'outil.

## Répondre à la requête d'authentification

L'outil renvoie le navigateur au point de terminaison d'autorisation de la plateforme, par GET ou POST. La plateforme lit
la requête, vérifie l'outil et son adresse de redirection, construit le message du membre connecté et le signe.

```php
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Platform\LaunchIssuer;

$issuer = new LaunchIssuer('https://courses.example.com', $tools, $keys, $clock);
$request = AuthenticationRequest::fromParameters($parameters);
[$tool, $redirect] = $issuer->check($request);         // before building anything

$post = $issuer->authorize($request, $hintOfTheMemberSignedIn, $message);
// render a form that posts $post->fields to $post->action, and submits itself
```

`AuthenticationRequest::fromParameters()` refuse une requête sans `scope=openid`, `response_type=id_token`,
`response_mode=form_post` et `prompt=none`, ou sans client, adresse de redirection, indice de connexion ou nonce.
`authorize()` refuse un outil ou un déploiement non enregistré, une adresse de redirection que l'outil n'a pas
enregistrée, un indice de connexion qui n'est pas celui du membre, et un message anonyme qui nomme la personne.
L'`id_token` porte `iss` la plateforme, `aud` et `azp` le client, `iat`, `exp` cinq minutes plus tard, le `nonce` de la
requête, et les revendications du message ; le formulaire le porte avec le `state` de la requête.

## Construire le message

`LaunchMessage` contient les revendications de LTI ; `toClaims()` les écrit.

```php
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;

$message = new LaunchMessage(
    MessageType::ResourceLink,
    deploymentId: '1',
    targetLinkUri: $target,
    person: new Person(subject: $opaqueIdOfTheMember, name: 'Ada Lovelace'),
    roles: Roles::of([ContextRole::Learner]),
    resourceLink: new ResourceLink('link-1', 'Week 1'),
    context: new Context('course-1', 'CS101', 'Introduction to computing'),
    custom: ['week' => '1'],
    ags: $agsEndpoint,
    nrps: $nrpsEndpoint,
);
```

Le sujet est stable pour le membre et cet outil, et opaque : la plateforme n'envoie jamais sa propre clé primaire ni le
courriel du membre comme sujet. Ne partagez le nom et le courriel que lorsque l'outil en a besoin.

## Le jeu de clés

La plateforme publie `KeyStore::publicKeys()->toJson()` à l'adresse du jeu de clés qu'elle donne à ses outils, avec le
type de média `application/jwk-set+json`. Voir les [clés](keys.md).

## Le point de terminaison des jetons et les services

Les outils demandent des jetons d'accès au point de terminaison des jetons, puis appellent les services : voir
[AGS](ags.md) et [NRPS](nrps.md).
