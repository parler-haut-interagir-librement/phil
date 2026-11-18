<!-- phil-dev source-sha256=d002bbbd32b73df750fe99400f4a8ec2eec9ce99f96600f9eb8537ce9963bacd -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | La version de `phil/checkin`. |
| `phil/checkin` | `^0.1` | Le poste d'accueil, la politique, les manifestes hors ligne et la synchronisation. |
| `doctrine/dbal` | `^4.5` | La table du registre. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | L'autorisation de chaque point de contrôle et le jeton CSRF sans état. |
| `symfony/routing`, `symfony/asset-mapper`, `symfony/stimulus-bundle` | `^8.2`, `^3.7` | Suggérés : les points de terminaison, le contrôleur servi sans étape de construction. |
| `doctrine/orm` | `^3.7` | Suggéré : la table dans le schéma que génère l'ORM et ses migrations. |

## Enregistrer le bundle

```sh
composer require phil/checkin-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CheckinBundle\PhilCheckinBundle::class => ['all' => true],
];
```

La protection CSRF de FrameworkBundle reste active : le bundle ajoute son jeton aux jetons sans état, qui n'ont besoin
d'aucune session.

## Les routes

```yaml
# config/routes/phil_checkin.yaml
phil_checkin:
    resource: '@PhilCheckinBundle/config/routes.php'
    prefix: /_checkin
```

Placez le préfixe derrière le pare-feu qui connecte le personnel. Les points de terminaison répondent 404 tant que
`enabled: true` n'est pas défini.

## La table

Avec l'ORM, `doctrine:migrations:diff` écrit la migration de la table `phil_checkin_scan`. Sans lui,
`DoctrineCheckInLedger::configureSchema()` ajoute la table à un schéma DBAL que l'application migre elle-même.

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. La présence d'un justificatif se calcule à partir de
ses lignes : celle du justificatif `Badge-7` n'est jamais combinée aux lignes de `badge-7`, un identifiant de scan
envoyé dans une autre casse est un autre scan, et deux justificatifs qui ne diffèrent que par la casse prennent chacun
leur propre séquence.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_checkin_scan` | `id`, `credential_id`, `checkpoint`, `device` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

#### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.

## La sécurité

Les points de terminaison demandent à la sécurité de l'application si l'utilisateur peut enregistrer à un point de
contrôle, avec l'attribut `PHIL_CHECKIN_SCAN` et le `CheckpointId` comme sujet. Sans *voter* qui l'accorde, personne
ne le peut :

```php
use Phil\Checkin\CheckpointId;
use Phil\CheckinBundle\PhilCheckinBundle;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, CheckpointId> */
final class DoorStaffVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return PhilCheckinBundle::SCAN === $attribute && $subject instanceof CheckpointId;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof Volunteer && $token->getUser()->staffs($subject->value);
    }
}
```

## La page du poste

La page produit le rendu du contrôleur avec la configuration de son point de contrôle ; voyez
[JavaScript](javascript.md).
