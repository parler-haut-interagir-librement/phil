<!-- phil-dev source-sha256=c87c3bb89481efd27481fc2ec6fbaa5cb0e72947f57b2c99eadbf95c0eaf9ed3 -->
# Mode d'incident

Le mode d'incident est celui de `phil/incident-mode-bundle` : sa table, ses préréglages, ses commandes de console, sa
page d'administration, sa levée automatique et les points de contrôle qui refusent une inscription, une invitation ou
un formulaire pendant qu'un incident les suspend. La modération ne branche pas la bibliothèque elle-même ; quand ce
bundle est enregistré, la file offre sa page et propose un incident quand les signalements affluent.

```sh
composer require phil/incident-mode-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\ModerationBundle\PhilModerationBundle::class => ['all' => true],
    Phil\IncidentModeBundle\PhilIncidentModeBundle::class => ['all' => true],
];
```

Les préréglages, les membres établis, le rôle de l'administration et la levée se configurent sous
`phil_incident_mode`, comme le montre la documentation de `phil/incident-mode-bundle` ; ses routes s'importent aussi
comme elle le montre :

```yaml
# config/routes/phil_incident_mode.yaml
phil_incident_mode_admin:
    resource: '@PhilIncidentModeBundle/config/routes/admin.php'
    prefix: /admin/incidents
```

## Dans la file

| Quand | La file montre |
|---|---|
| Le membre détient le rôle de `phil_incident_mode.admin.role` | Un lien vers la page des incidents, `phil_incident_mode_admin`, à côté de la file |
| Ce rôle, `surge.reports` signalements en `surge.within` secondes, aucun incident en vigueur | Une alerte disant que les signalements affluent, avec un lien vers cette page |
| Un autre modérateur | Ni l'un ni l'autre : la page n'est pas la sienne |
| L'administration du mode d'incident désactivée (`admin: false`), ou le bundle non enregistré | Ni l'un ni l'autre |

`Infrastructure\IncidentMode\IncidentProposal` répond aux deux questions à partir de l'`IncidentMode` du bundle, de
l'afflux de signalements de la modération et du rôle, que le bundle publie comme paramètre de conteneur
`phil_incident_mode.admin.role` tant que son administration est activée. Il ne garde rien d'une requête à l'autre.

## Dans les pages du site

Les points de contrôle sont ceux du bundle, tout comme leurs fonctions : un site refuse une inscription, une
invitation ou un formulaire pendant qu'un incident le suspend, et montre un avis, avec eux.

```php
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;

#[SuspendedDuring('signups', methods: ['POST'])]
public function register(Request $request): Response
```

```yaml
# a route refused while invitations are suspended
invite:
    path: /invite
    controller: App\Controller\InvitationController
    defaults: { _suspended_during: invitations }
```

```php
// a form invalid while new posts are suspended, with the error that says until when
$resolver->setDefault('suspended_during', ['new-posts']);
```

```twig
{% if phil_incident_suspended('signups') %}
    <p>Sign-ups are suspended for now.</p>
{% endif %}
```

Une requête refusée reçoit un 503, ou le statut que choisit la configuration du bundle, avec `Retry-After`. Les
membres établis sont épargnés des restrictions que liste `phil_incident_mode.spare_established`, par le port
`Phil\IncidentMode\Contract\EstablishedMembers`, que nomme `phil_incident_mode.established_members`.

## Renseignements personnels

Les incidents qu'un membre a déclenchés ou levés répondent aux demandes d'accès et d'effacement de
`phil/privacy-bundle` par l'exportateur et l'effaceur propres au mode d'incident ; les enregistrements de la modération
les laissent de côté ([Renseignements personnels](privacy.md)).

## Mise à niveau depuis 0.2

Le bundle ne câble plus `phil/incident-mode` lui-même :

| En 0.2 | À partir de la 0.3 |
|---|---|
| `phil_moderation.incident.role` | `phil_incident_mode.admin.role` |
| `phil_moderation.incident.presets`, durées en minutes | `phil_incident_mode.presets`, par nom, durées écrites `90m`, `2h` ou `1d` |
| `phil_moderation.incident.without` | `phil_incident_mode.without_presets` |
| `phil_moderation.incident.spare_established` | `phil_incident_mode.spare_established`, avec `established_members` qui nomme le service d'`EstablishedMembers` |
| Les pages `phil_moderation_incidents`, `phil_moderation_incident_start`, `phil_moderation_incident_lift` | La page `phil_incident_mode_admin` du bundle, qui prolonge aussi un incident |
| `ModerationAdmin::incidentsMenuItem()` | Un élément de menu vers la route `phil_incident_mode_admin` |
| `phil_moderation_suspended('signups')` | `phil_incident_suspended('signups')` |
| `CurrentMemberRestrictions::appliesNow()` | `Phil\IncidentModeBundle\Application\Checkpoint::applies()`, ou `guard()`, qui refuse |
| Les incidents dans l'export de la modération sous `incidents` | L'export du mode d'incident |

La table `phil_incident_activation` garde son nom et ses rangées : le magasin du bundle les lit sur la connexion de
`phil_incident_mode.connection`, `default` sauf configuration. Retirez la clé `incident` de `phil_moderation`, que la
configuration refuse désormais, enregistrez le bundle et importez ses routes.
