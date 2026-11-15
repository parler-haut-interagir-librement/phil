<!-- phil-dev source-sha256=79da2d11d383a168ba3f3a13648d08c5d2e57ab17bd7bd93bb93fb217a07ec42 -->
# Mode d'incident

Quand `phil/incident-mode` 0.2 ou plus récent est installé, le bundle le câble ; il n'y a rien à déclarer.

```sh
composer require phil/incident-mode
```

| Service | Ce que c'est |
|---|---|
| `Phil\IncidentMode\Presets` | Les préréglages fournis, avec `incident.presets` ajoutés et `incident.without` écartés |
| `Phil\IncidentMode\Contract\ActivationStore` | Le magasin DBAL, sur la connexion du bundle ; sa table rejoint le schéma que l'ORM génère |
| `Phil\IncidentMode\Contract\LiftScheduler` | Le message différé `LiftIncident` quand Messenger a un bus ; rien sinon |
| `Phil\IncidentMode\IncidentMode` | Le mode d'incident, public, ses événements sur le répartiteur d'événements |
| `LiftIncidentHandler`, `LiftExpiredIncidentsHandler` | Les handlers de la levée, quand Messenger a un bus |
| `Phil\IncidentMode\Contract\EstablishedMembers` | `Phil\IncidentMode\NoEstablishedMembers` tant que l'application n'a pas donné son propre alias |
| `Phil\IncidentMode\IncidentRestrictions` | Si une restriction s'applique à un membre, avec `incident.spare_established` |
| `Infrastructure\IncidentMode\CurrentMemberRestrictions` | La même question pour le membre que lit `CurrentMember` |

Acheminez `Phil\IncidentMode\Messenger\LiftIncident` vers un transport qui prend en charge les délais, et ajoutez le
`LiftExpiredIncidents` récurrent à un calendrier, comme le montre la documentation de `phil/incident-mode`.

## Dans l'interface d'administration

La page du mode d'incident est réservée à l'attribut de sécurité `incident.role`. Elle montre les incidents en vigueur
avec leur fin et un bouton qui lève chacun, le formulaire qui en déclenche un à partir d'un préréglage — **la durée est
obligatoire**, le préréglage la propose et la borne —, et le journal des incidents. Le modérateur est l'acteur de ce
qu'il déclenche ou lève, par son identifiant stable.

**La file propose le mode d'incident** à ce rôle quand `surge.reports` signalements sont arrivés en `surge.within`
secondes et qu'aucun incident n'est en vigueur.

## Dans les pages du site

```twig
{% if phil_moderation_suspended('signups') %}
    <p>Sign-ups are suspended for now.</p>
{% endif %}
```

En PHP, l'application demande `Phil\IncidentMode\IncidentRestrictions::appliesTo('new-posts', $member)`, avec
l'identifiant stable du membre ou `null` pour un visiteur — ou `CurrentMemberRestrictions::appliesNow('new-posts')`
pour le membre de la requête courante — avant de laisser quelqu'un publier, et `IncidentMode::isSuspended('signups')`
avant de laisser quelqu'un s'inscrire.

## Les membres établis

Un incident peut laisser les membres établis libres de certaines de ses restrictions : un forum qui suspend les
nouveaux messages après une vague de pourriel garde ses habitués actifs.

```yaml
phil_moderation:
    incident:
        spare_established: [new-posts, new-comments]
```

L'application dit qui est établi avec un service à elle qui implémente le port
`Phil\IncidentMode\Contract\EstablishedMembers` de `phil/incident-mode` — les membres d'un niveau de confiance, ou d'une
ancienneté et d'une activité —, auquel elle donne un alias ; par défaut personne ne l'est, de sorte que chaque incident
restreint tout le monde. Le port reçoit l'identifiant stable du membre, la chaîne que renvoie
`ModerationMember::moderationIdentifier()` :

```php
use Phil\IncidentMode\Contract\EstablishedMembers;

final readonly class RegularMembers implements EstablishedMembers
{
    public function __construct(private MemberDirectory $members)
    {
    }

    public function isEstablished(string $member): bool
    {
        return $this->members->postsOf($member) >= 50;
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\IncidentMode\Contract\EstablishedMembers: '@App\Moderation\RegularMembers'
```

| La restriction | S'applique à |
|---|---|
| Non suspendue | Personne |
| Suspendue, absente de `spare_established` | Tout le monde |
| Suspendue et énumérée | Tout le monde sauf les membres établis |
| Suspendue et énumérée, pendant que `read-only` est en vigueur | Tout le monde, sauf si `read-only` est énumérée aussi |

Un visiteur n'est jamais épargné. Les noms sont ceux de `Phil\IncidentMode\Restriction`, ou ceux de l'application.
