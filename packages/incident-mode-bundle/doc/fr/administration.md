<!-- phil-dev source-sha256=9002c291a4d08c08012c47c24a3c2292f4122dbe1870424bda512e95b9a77a74 -->
# Administration

La page est servie par les routes de `@PhilIncidentModeBundle/config/routes/admin.php`, sous le préfixe que choisit
l'application :

| Route | Méthode et chemin | Ce qu'elle fait |
|---|---|---|
| `phil_incident_mode_admin` | `GET /` | La page : les incidents en vigueur, les préréglages, le journal — `?page=2` pour la page suivante |
| `phil_incident_mode_admin_start` | `POST /start` | Démarre un incident depuis `preset`, pour `amount` de `unit` (`minutes`, `hours`, `days`), avec un `reason` facultatif |
| `phil_incident_mode_admin_extend` | `POST /{id}/extend` | Donne à l'incident une fin plus tardive, de `amount` de `unit` |
| `phil_incident_mode_admin_lift` | `POST /{id}/lift` | Lève l'incident maintenant |

## La page

- **Les incidents en vigueur** : le libellé du préréglage, ce qu'il suspend, sa fin dans le fuseau horaire configuré, sa
  raison, combien de temps de plus son préréglage lui permet de durer avec le formulaire qui le prolonge, et le bouton
  qui le lève.
- **Les préréglages** : un formulaire chacun, rempli avec la durée proposée dans sa plus grande unité entière, avec la
  plus longue durée que le préréglage permet, une raison conservée dans le journal, et le bouton qui démarre l'incident.
- **Le journal** : chaque incident, le plus récent d'abord — préréglage, début, fin ou levée, façon dont il a pris fin,
  qui l'a démarré, qui l'a levé, pourquoi —, `admin.page_size` à la fois par page.

Un formulaire que la bibliothèque refuserait est affiché de nouveau avec le statut 422 et ce qui a échoué : aucun
préréglage de ce nom, une durée hors des bornes, une durée plus longue que ce que permet le préréglage, une raison de
plus de 500 caractères, un incident qui n'est plus en vigueur. La page n'est jamais conservée par un cache.

## Qui peut l'utiliser

Le membre connecté détient le rôle de `admin.role`, `ROLE_ADMIN` par défaut ; toute autre personne rencontre le
pare-feu, comme pour n'importe quelle page refusée, et tout le monde aussi lorsqu'aucun vérificateur d'autorisation
n'est enregistré. Le rôle est aussi le paramètre
`phil_incident_mode.admin.role` (`IncidentModeServices::ADMIN_ROLE_PARAMETER`), défini seulement quand l'administration
est activée, pour qu'un autre bundle offre un lien vers la page à ce rôle seul. L'acteur de ce qu'il démarre, prolonge ou lève est l'identifiant que donne `CurrentMember` : par
défaut, l'identifiant de l'utilisateur de Symfony Security. **Un identifiant qui ressemble à une adresse courriel est
refusé**, puisqu'il nomme la personne à quiconque lit le journal et les changements ; une application dont les
utilisateurs se connectent avec leur adresse les nomme par son propre `CurrentMember` :

```php
namespace App\Security;

use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class MemberUuid implements CurrentMember
{
    public function __construct(private Security $security)
    {
    }

    public function identifier(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof \App\Entity\User ? $user->uuid() : null;
    }
}
```

```yaml
phil_incident_mode:
    current_member: App\Security\MemberUuid
```

## Les formulaires

Chaque formulaire porte le jeton `phil_incident_mode` dans le champ `_token`. Le bundle ajoute le jeton aux jetons sans
état de FrameworkBundle, de sorte que la page n'a pas besoin de session ; un formulaire envoyé depuis un autre site — son
en-tête `Sec-Fetch-Site` dit `cross-site` — ou sans jeton valide est refusé avec le statut 400.

## L'apparence

Les pages dessinent les recettes du kit — `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Table`,
`Textarea` —, que le kit PHIL UX et le kit Shadcn de Symfony UX Toolkit ont tous deux, par la fonction Twig
`phil_incident_kit('Button')`, qui préfixe le sous-répertoire des composants. Elles étendent le gabarit de `layout`, qui
remplit les blocs `title` et `main` ; une application donne le sien, ou remplace les gabarits sous
`templates/bundles/PhilIncidentModeBundle/`. Aucun script n'est nécessaire.

## La console

Les mêmes actions s'exécutent depuis la console, les moments en UTC :

```sh
php bin/console phil:incident:start suspend-signups --duration=2h --actor=8d2e0f --reason="Spam wave"
php bin/console phil:incident:extend 3f2a… --by=1h --actor=8d2e0f
php bin/console phil:incident:lift 3f2a… --actor=8d2e0f
php bin/console phil:incident:lift --expired
php bin/console phil:incident:list
php bin/console phil:incident:list --journal --limit=20 --offset=20
php bin/console phil:incident:list --presets
```

La durée et l'acteur sont obligatoires : un incident ne dure jamais sans fin, et le journal dit toujours qui l'a
démarré, par un identifiant stable. Une durée s'écrit `90`, `45m`, `2h`, `3d` ou `1d 12h`. Un refus de la bibliothèque
— un préréglage, une durée plus longue que ce qu'il permet, une adresse comme acteur — termine la commande par un échec
et sa raison.
