<!-- phil-dev source-sha256=24e3c5451a10b86c7acf083f24606d03687299056dc542151881fc07e065dc58 -->
# Points de contrôle

Un point de contrôle demande si un incident suspend, pour le membre de la requête, ce qu'il s'apprête à faire. Un
membre établi est épargné des restrictions de `spare_established` ; un visiteur ne l'est jamais.

## Un contrôleur

```php
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;

final class RegistrationController
{
    #[SuspendedDuring('signups', methods: ['POST'])]
    public function register(Request $request): Response
    {
        // …
    }
}
```

`#[SuspendedDuring]` prend une restriction ou une liste de restrictions, et les méthodes HTTP qu'il refuse — toutes les
méthodes quand aucune n'est nommée. Sur une classe, il couvre chaque action ; il peut être répété. Ne refuser que `POST`
laisse la page montrer son formulaire pendant que la bannière dit pourquoi l'envoyer serait refusé ; refuser toutes les
méthodes montre plutôt la page de refus.

## Une route

Une route d'un autre bundle ne porte aucun attribut de celui-ci : ses valeurs par défaut nomment plutôt les
restrictions.

```yaml
# config/routes.yaml
invitation_send:
    path: /invitations
    controller: Acme\InvitationBundle\Controller\InvitationController::send
    defaults:
        _suspended_during: [invitations]
```

## Un chemin de code

```php
use Phil\IncidentModeBundle\Application\Checkpoint;

final readonly class Invitations
{
    public function __construct(private Checkpoint $checkpoint)
    {
    }

    public function send(Invitation $invitation): void
    {
        $this->checkpoint->guard('invitations');
        // …
    }
}
```

| Méthode | Réponse |
|---|---|
| `applies('signups')` | Si la restriction s'applique maintenant au membre de la requête |
| `suspension('invitations', 'signups')` | La première qui s'applique, sous forme de `Suspension` avec sa fin ; `null` si aucune ne s'applique |
| `suspensions()` | Chaque restriction qui s'applique maintenant, une fois chacune, dans l'ordre où les incidents ont démarré |
| `guard('signups')` | Lève `SuspendedByIncident` tant que l'une d'elles s'applique |

La fin d'une suspension est la fin la plus tardive des incidents en vigueur qui la suspendent. Chaque réponse lit les
incidents en vigueur à ce moment-là ; rien n'est conservé entre deux requêtes. Un visiteur est servi à partir d'une
seule lecture de ces incidents.

## Un formulaire

```php
$form = $this->createForm(RegistrationType::class, $registration, ['suspended_during' => 'signups']);
```

L'option `suspended_during`, sur chaque formulaire, prend une restriction ou une liste de restrictions. Une soumission
pendant que l'une d'elles s'applique est invalide, avec l'erreur qui dit ce qui est suspendu et jusqu'à quand — la
`Suspension` en est la cause —, de sorte que rien n'en est enregistré.

## Le refus

`SuspendedByIncident` est une erreur HTTP : le statut de `checkpoints.status`, 503 par défaut, et un en-tête
`Retry-After` avec le nombre de secondes jusqu'à la fin. Le bundle en produit le rendu avec
`@PhilIncidentMode/suspended.html.twig`, dans la langue du visiteur, jamais conservé par un cache partagé. Sans Twig,
Symfony en produit le rendu comme celui de n'importe quelle erreur HTTP, avec le même statut et le même en-tête.

## Ce que lisent les visiteurs

Les mots disent honnêtement ce qui est suspendu et jusqu'à quand, dans la langue de la requête et dans le fuseau
horaire de `timezone`, qu'ils nomment : *Sign-ups are suspended until November 17, 2026 at 12:00 PM (Eastern Standard
Time).* `read-only` dit que le site peut être lu, mais que rien ne peut être ajouté ni modifié ; une restriction propre à
l'application est nommée telle qu'elle est écrite.

| Où | Quoi |
|---|---|
| `{{ include('@PhilIncidentMode/_banner.html.twig') }}` | Une bannière dans la mise en page du site, rien quand aucun incident ne restreint le membre |
| La route `phil_incident_mode_status` | La page publique de ce qui est suspendu maintenant, `200` |
| La route `phil_incident_mode_notice` | Le même avis sans la mise en page, pour une page qui le charge |
| `phil_incident_suspended('signups')` | Si l'on doit montrer un formulaire ou l'avis |
| `phil_incident_suspensions()` | Les suspensions, pour un avis propre à l'application |
| `phil_incident_sentence(suspension)` | La phrase d'une suspension |
| `phil_incident_moment(date)` | Un moment dans la langue du visiteur et le fuseau horaire configuré |

Chaque restriction de l'avis porte `data-restriction` et `data-until`, sa fin en UTC, pour une page qui veut l'écrire
dans le fuseau horaire du navigateur.
