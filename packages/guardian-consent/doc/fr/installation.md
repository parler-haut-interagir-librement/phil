<!-- phil-dev source-sha256=49140f13adaee71d52df4d3935f0b3187c6ced4127602c1321f626c22c784389 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `sodium` | Les jetons de confirmation, écrits en Base64 compatible avec les URL, en temps constant. |
| `psr/clock` | `^1.0` | La date du jour dans la juridiction du membre, et les instants des demandes et des preuves. |
| `psr/event-dispatcher` | `^1.0` | Les événements après une demande, une décision, un retrait, un effacement et un passage de relais. |
| `symfony/service-contracts` | `^3.6` | `ResetInterface`, implémenté par les dépôts en mémoire. |
| `symfony/clock` | `^8.2`, suggéré | Une horloge à fournir au service, et une horloge figée dans les tests. |

```sh
composer require phil/guardian-consent
```

## Les services

`GuardianConsent` prend un dépôt de mineurs, un dépôt de demandes, un dépôt de preuves, un notificateur, un
calculateur d'empreintes de jetons et une horloge ; puis, facultativement, les juridictions servies (le Québec seul par
défaut), un répartiteur d'événements, un lien de tuteur, le nombre de jours pendant lesquels une demande peut recevoir
une réponse (7 par défaut, de 1 à 30) et le nombre de jours pendant lesquels une demande sans réponse est conservée
après son expiration (30 par défaut, de 0 à 365).

`TokenHasher` prend la clé avec laquelle les liens de confirmation sont signés, d'au moins 32 octets, lue dans le
coffre des secrets, suivie des clés qu'elle a remplacées, s'il y en a.

Dans une application Symfony, avec ses propres dépôts et son propre notificateur :

```yaml
# config/services.yaml
services:
    Phil\GuardianConsent\Contract\MinorStore: '@App\Consent\DoctrineMinorStore'
    Phil\GuardianConsent\Contract\RequestStore: '@App\Consent\DoctrineRequestStore'
    Phil\GuardianConsent\Contract\ProofStore: '@App\Consent\DoctrineProofStore'
    Phil\GuardianConsent\Contract\Notifier: '@App\Consent\MailerNotifier'

    Phil\GuardianConsent\TokenHasher:
        arguments: ['%env(GUARDIAN_CONSENT_KEY)%']

    Phil\GuardianConsent\Jurisdictions:
        factory: ['App\Consent\Jurisdictions', 'create']

    Phil\GuardianConsent\GuardianConsent:
        arguments:
            $clock: '@clock'
            $events: '@event_dispatcher'
            $requestLifetimeDays: 7
            $retentionDays: 30

    Phil\GuardianConsent\GuardianConsentRights: ~
```

`GuardianConsent::sweep()` s'exécute une fois par jour, depuis un planificateur : voir
[Concepts](concepts.md#le-balayage).

Les dépôts en mémoire, `InMemoryMinorStore`, `InMemoryRequestStore` et `InMemoryProofStore`, servent aux tests et aux
démonstrations.
