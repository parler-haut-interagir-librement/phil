<!-- phil-dev source-sha256=9223b85f19f0901fb2e7637abec1c1a087067cead91971be9c12c53bf5225937 -->
# Intégrations

Ce que le bundle branche autour d'`ActiveTenant` : le contexte du locataire, l'écouteur de requêtes, le changement,
l'argument `#[CurrentTenant]`, Messenger, la console et Twig. Le locataire de chaque hôte, les boutiques,
l'authentification par locataire et le courrier par locataire ont chacun leur page : [hosts-and-shops.md](hosts-and-shops.md),
[authentication.md](authentication.md) et [mail.md](mail.md).

## Le contexte du locataire

`Application\TenantContext` contient le locataire dans lequel agit l'unité de travail courante : une requête, un
message, une commande. `ActiveTenant` décide dans quel locataire agit un compte ; le contexte garde cette décision
pour le code qui s'exécute ensuite.

| Méthode | Comportement |
|---|---|
| `current(): ?TenantId` | Le locataire actif, ou `null` |
| `require(): TenantId` | Le locataire actif ; lance `NoActiveTenant` quand il n'y en a pas |
| `enter(TenantId $tenant): void` | Rend le locataire actif et appelle `activate()` sur chaque activation ; quand l'une échoue, chaque activation est désactivée, aucun locataire n'est actif, et l'échec est lancé de nouveau |
| `leave(): void` | Ne rend aucun locataire actif et appelle `deactivate()` sur chaque activation, toutes même quand l'une échoue ; le premier échec est lancé une fois qu'elles sont toutes atteintes |
| `within(TenantId $tenant, callable $work): mixed` | Exécute le travail dans le locataire, puis revient au locataire actif auparavant, ou à aucun, même quand le travail lance une exception ou que le locataire ne peut être atteint |

On quitte le contexte entre les requêtes (`kernel.reset`) : un serveur de longue durée ne transporte donc jamais un
locataire d'une requête à la suivante.

### Activations

Une activation suit le contexte : `Application\Contract\TenantActivation`, avec `activate(TenantId)` et `deactivate()`.
Le bundle en fournit une, `DoctrineTenantActivation`. Elle règle le locataire sur le filtre `phil_tenancy` de chaque
gestionnaire d'entités où il est activé et, à la sortie, réactive le filtre à neuf : une nouvelle instance, sans
locataire, qui ne correspond à aucune ligne liée à un locataire. Un gestionnaire dont le filtre est désactivé ou
suspendu pour un travail entre locataires est laissé tranquille.

Ajoutez les vôtres pour tout ce qui suit le locataire : un espace de noms de cache, un contexte de journal, un préfixe
de stockage. Tout service qui implémente l'interface reçoit l'étiquette `phil_tenancy.activation` par configuration
automatique :

```php
final readonly class TenantLogContext implements TenantActivation
{
    public function __construct(private TenantLogProcessor $processor) {}

    public function activate(TenantId $tenant): void { $this->processor->tenant = $tenant->value; }

    public function deactivate(): void { $this->processor->tenant = null; }
}
```

## L'écouteur de requêtes

`Infrastructure\Http\ResolveActiveTenant` s'exécute sur chaque requête principale, sur `kernel.request`, sans priorité
propre. Son étiquette le place après `router_listener`, pour que la route soit connue, et après `security.firewall`
(ou `debug.security.firewall`, son remplaçant traçable en débogage), pour que les locataires atteignables puissent
lire le compte authentifié. Un écouteur de l'application qui doit s'exécuter avant ou après lui nomme
`ResolveActiveTenant::class` dans son propre `before` ou `after`.

| Résolution | Ce que fait l'écouteur |
|---|---|
| `Outcome::Active` | Entre dans le locataire : le filtre est réglé avant la première requête |
| `Outcome::SelectionRequired`, avec `request.selector_route` | Redirige vers le sélecteur ; sur une requête sûre (`GET`, `HEAD`), avec l'URI demandée comme `target` |
| `Outcome::SelectionRequired`, sans route de sélecteur | Continue hors de tout locataire |
| `Outcome::NoneReachable` | Continue hors de tout locataire : le compte reste connecté, et les entités liées à un locataire ne correspondent à rien |

Les sous-requêtes partagent le locataire de la requête principale. La route du sélecteur, `phil_tenancy_switch` et
`request.ignored_routes` ne sont pas résolues, pour qu'un compte qui doit choisir puisse les atteindre ; elles
s'exécutent hors de tout locataire.

Une requête déjà dans un locataire, celle dont l'hôte le sert, n'est pas résolue de nouveau, mais un compte connecté
doit pouvoir atteindre ce locataire (`ActiveTenant::canReach()`), quelle que soit la route : sinon l'écouteur lance une
`AccessDeniedHttpException`, un `403`. Avec les locataires atteignables et cet écouteur,
[l'authentification par locataire](authentication.md) laisse passer jusqu'à l'hôte d'un locataire une session ouverte
hors de tout locataire, sur un domaine central dont les hôtes partagent le témoin ; cette vérification la tient hors des
locataires que son compte ne peut pas atteindre. Un visiteur qui n'est pas connecté
(aucun jeton, ou un jeton sans utilisateur) reste dans le locataire de l'hôte. L'écouteur lit `security.token_storage`
quand SecurityBundle l'enregistre ; sans lui, rien ne distingue un visiteur d'un compte, et les locataires atteignables
décident pour chaque requête.

## Le changement

Le contrôleur du bundle reçoit le formulaire du sélecteur : importez sa route, puis envoyez-lui le formulaire.

```php
// config/routes/phil_tenancy.php
return static function (RoutingConfigurator $routes): void {
    $routes->import('@PhilTenancyBundle/config/routes.php');
};
```

```twig
<form method="post" action="{{ path('phil_tenancy_switch') }}">
    <input type="hidden" name="target" value="{{ app.request.query.get('target') }}">
    <select name="tenant">
        {% for tenant in reachable_tenants() %}
            <option{% if tenant == active_tenant() %} selected{% endif %}>{{ tenant }}</option>
        {% endfor %}
    </select>
    <button>Switch</button>
</form>
```

| Requête | Réponse |
|---|---|
| `POST /tenant/switch`, un `tenant` atteignable, depuis ce site | `ActiveTenant::switchTo()`, puis une redirection vers `target` |
| `target` absent ou qui n'est pas un chemin de ce site | Une redirection vers la racine du site : `//host`, `/\host`, une URL absolue ou relative, et une cible qui contient un caractère de contrôle ou une espace ne sont jamais suivis (les navigateurs retirent une tabulation ou un saut de ligne, si bien que `/<TAB>/host` serait lu comme `//host`) |
| Un locataire hors de portée, ou une référence mal formée | 403 ; le choix mémorisé reste tel qu'il était |
| Envoyée depuis un autre site | 403 |

Le formulaire n'est accepté que depuis ce site : `Sec-Fetch-Site: same-origin` quand le navigateur l'envoie, sinon un
`Origin` qui est celui du site. Cette vérification remplace un jeton CSRF pour ce formulaire ; un navigateur qui
n'envoie aucun de ces en-têtes est refusé.

## `#[CurrentTenant]`

```php
use Phil\TenancyBundle\Infrastructure\Attribute\CurrentTenant;

public function invoices(#[CurrentTenant] TenantId $tenant): Response { /* … */ }

public function home(#[CurrentTenant] ?TenantId $tenant): Response { /* … */ }
```

L'argument reçoit le locataire du contexte. Quand aucun locataire n'est actif, un argument nullable reçoit `null`, et
tout autre donne une 403.

## Messenger

Ajoutez le *middleware* aux bus dont les messages agissent dans un locataire :

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware
```

| Enveloppe | Ce que fait le *middleware* |
|---|---|
| Répartie dans un locataire, sans `TenantStamp` | Ajoute un `TenantStamp` avec la référence du locataire |
| Répartie avec un `TenantStamp` déjà présent | Le garde |
| Répartie hors de tout locataire | N'ajoute rien |
| Reçue d'un transport avec un `TenantStamp` | La traite dans ce locataire, puis ramène le *worker* à son propre locataire |
| Reçue sans estampille | La traite dans le locataire du *worker*, en général aucun, où le filtre échoue en mode fermé |

L'estampille porte la référence sous forme de chaîne, que tout sérialiseur de transport conserve, et rien d'autre du
locataire : ni nom, ni réglage, ni donnée personnelle. Son constructeur refuse une valeur qui n'est pas une référence
valide. Un message traité de façon synchrone est traité dans le locataire de celui qui l'a réparti.

Les messages restent courts et portent des identifiants : un message qui agit sur un contenu lourd porte l'identifiant
de ce contenu, ou emploie le *claim check* de Messenger, plutôt que le contenu lui-même. Un *worker* quitte le locataire
après chaque message, et Symfony réinitialise les services entre les messages (`kernel.reset`) : aucun locataire n'est
donc transporté d'un message au suivant. Le courrier mis en file dans un locataire est envoyé dans ce locataire
([mail.md](mail.md)).

## La console

Chaque commande reçoit une option `--tenant` et s'exécute dans ce locataire :

```bash
bin/console app:monthly-report --tenant=retail
```

Une ligne de commande appartient à un opérateur, pas à un compte : le locataire n'est pas vérifié contre les
locataires atteignables. Sans l'option, la commande s'exécute hors de tout locataire. Une commande qui déclare son
propre `--tenant` le garde, et le bundle ne s'en mêle pas. Une référence mal formée arrête la commande avec
`InvalidValue`. `console: false` désactive l'option.

## Twig

| Fonction | Retourne |
|---|---|
| `active_tenant()` | La référence du locataire actif, ou `null` |
| `reachable_tenants()` | Les références vers lesquelles le compte peut changer, dans l'ordre du fournisseur |

Les deux sont enregistrées quand `twig/twig` est installé et que `reachable_tenants` est configuré.
