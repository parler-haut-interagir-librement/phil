<!-- phil-dev source-sha256=952a37b2e426e288aa56d47ff353ae055dbacf7cba6b7b2b02dff81317f23598 -->
# Résolution et changement

`Phil\TenancyBundle\Domain\Resolution\ActiveTenantRule` contient les deux règles du locataire actif, sous forme de
fonctions statiques pures de l'ensemble atteignable. Aucune ne demande quoi que ce soit au fournisseur d'identité.
`ActiveTenant` les applique aux ports de l'application ; appelez la règle directement quand vous détenez vous-même
les valeurs.

## La règle de résolution

```php
ActiveTenantRule::resolve(?TenantId $remembered, ReachableTenants $reachable): Resolution
```

| Choix mémorisé | Ensemble atteignable | Issue | Locataire | `staleChoiceDropped` |
|---|---|---|---|---|
| atteignable | le contient | `Active` | celui mémorisé | `false` |
| non atteignable | exactement un locataire | `Active` | le seul locataire | `true` |
| non atteignable | plusieurs locataires | `SelectionRequired` | aucun | `true` |
| non atteignable | vide | `NoneReachable` | aucun | `true` |
| aucun | exactement un locataire | `Active` | le seul locataire | `false` |
| aucun | plusieurs locataires | `SelectionRequired` | aucun | `false` |
| aucun | vide | `NoneReachable` | aucun | `false` |

En mots :

1. Un choix mémorisé encore atteignable est le locataire actif.
2. Un choix mémorisé qui n'est plus atteignable est abandonné, jamais remplacé par un voisin, et l'appelant reçoit
   l'instruction de l'oublier.
3. Sans choix utilisable, un seul locataire atteignable est actif, puisqu'il n'y a rien à choisir.
4. S'il y en a plusieurs, une sélection est requise : le premier locataire d'une liste est un hasard de tri, pas une
   décision.
5. S'il n'y en a aucun, aucun locataire n'est actif, et le compte reste authentifié.

**Pourquoi un choix abandonné doit être oublié.** Une appartenance révoquée puis accordée de nouveau est une nouvelle
décision ; l'ancien choix ne doit pas revenir discrètement avec elle. `ActiveTenant::resolve()` appelle
`ActiveTenantStore::forget()` pour vous.

## La règle du changement

```php
ActiveTenantRule::switchTo(TenantId $requested, ReachableTenants $reachable): TenantId
```

Retourne le locataire demandé quand l'ensemble le contient, et lance `TenantNotReachable` sinon, y compris quand
l'ensemble est vide. `ActiveTenant::switchTo()` mémorise le locataire retourné ; en cas de refus, le choix mémorisé
reste tel qu'il était.

## `Resolution`

`Phil\TenancyBundle\Domain\Resolution\Resolution`, immuable.

| Membre | Comportement |
|---|---|
| `public Outcome $outcome` | `Active`, `SelectionRequired` ou `NoneReachable` |
| `public ReachableTenants $reachable` | L'ensemble contre lequel la résolution a été faite ; vide pour `NoneReachable` |
| `public bool $staleChoiceDropped` | Un choix mémorisé n'était plus atteignable et a été ignoré |
| `isActive(): bool` | `Outcome::Active === $outcome` |
| `tenant(): TenantId` | Le locataire actif ; lance `NoActiveTenant` sinon (`selectionRequired()` ou `noneReachable()`) |
| `tenantOrNull(): ?TenantId` | Le locataire actif, ou `null` |
| `static active(TenantId, ReachableTenants, bool $staleChoiceDropped = false)` | Constructeur nommé |
| `static selectionRequired(ReachableTenants, bool $staleChoiceDropped = false)` | Constructeur nommé |
| `static noneReachable(bool $staleChoiceDropped = false)` | Constructeur nommé |

## `Outcome`

`Phil\TenancyBundle\Domain\Resolution\Outcome`, une énumération adossée à des chaînes.

| Cas | Valeur | Ce que fait l'application |
|---|---|---|
| `Active` | `active` | Règle le locataire sur le filtre Doctrine et continue |
| `SelectionRequired` | `selection_required` | Affiche sa page de sélection, qui liste `$resolution->reachable` |
| `NoneReachable` | `none_reachable` | Garde le compte connecté, hors de tout locataire ; les données liées à un locataire restent invisibles |

## Le sélecteur

Le bundle possède les **règles** du sélecteur et son **changement** : la résolution dit quand une sélection est
requise, la règle du changement dit ce qu'une sélection peut choisir, et la route `phil_tenancy_switch` l'applique
([integrations.md](integrations.md)). L'application possède la **page** : son rendu, la liste déroulante, et si
l'entrée dans un locataire exige une nouvelle authentification à un niveau d'assurance plus élevé (demandée au
fournisseur d'identité par une nouvelle demande d'autorisation, jamais mémorisée par lui pour un locataire).

```php
#[Route('/tenant/select', name: 'tenant_select', methods: ['GET'])]
public function select(): Response
{
    return $this->render('tenant/select.html.twig');
}
```

```twig
{# templates/tenant/select.html.twig #}
<form method="post" action="{{ path('phil_tenancy_switch') }}">
    <input type="hidden" name="target" value="{{ app.request.query.get('target') }}">
    {% for tenant in reachable_tenants() %}
        <button name="tenant" value="{{ tenant }}">{{ tenant }}</button>
    {% endfor %}
</form>
```

Nommez la route dans `request.selector_route` : l'écouteur y envoie un compte qui doit choisir, et la laisse non
résolue pour que le compte puisse l'atteindre.
