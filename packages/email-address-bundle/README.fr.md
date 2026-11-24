<!-- phil-dev source-sha256=9046b9381432f8a44b218883248a4e25643ef30705a292152721f569ccc7c81a -->
# phil/email-address-bundle

**Accepte les adresses courriel selon une politique par site et par liste, suggère dans le navigateur l'adresse voulue, et
transforme les rebonds et les plaintes en décisions, dans Symfony.**

phil/email-address-bundle intègre `phil/email-address` et `phil/bounce-parser` à Symfony. Les bibliothèques seules
conviennent à une application sans Symfony.

- **La contrainte sur le conteneur.** Le `DeliverableEmailAddress` de la bibliothèque reçoit les listes et le résolveur
  du conteneur ; `AcceptedEmailAddress` applique la politique du bundle, celle du site courant et celle d'une liste,
  superposées : serveur de courriel recherché ou non, domaines jetables, domaines refusés, seuls domaines acceptés.
- **Le champ de courriel.** `EmailAddressType` suggère l'adresse voulue quand son domaine est une faute de frappe d'un
  domaine connu — `ada@gmial.com` — et avertit d'une adresse de rôle comme `info@`, sans refuser ni l'une ni l'autre :
  dans le navigateur par un contrôleur Stimulus et une région vivante, et sans JavaScript comme aide du champ une fois
  le formulaire soumis.
- **La forme normalisée.** `EmailAddresses` compare deux adresses, les indexe par une empreinte qui ne redonne pas
  l'adresse, et reconnaît une adresse de rôle ; il garde les points et le `+tag` de l'adresse de la personne.
- **Les règles de rebond.** Un rebond définitif suspend l'adresse ; les rebonds temporaires sont comptés jusqu'à un seuil
  dans une fenêtre ; une plainte authentifiée désabonne, et celle dont aucun authentificateur ne répond va à la
  révision. Chaque décision est un événement de Symfony, et peut se poursuivre dans Messenger avec des identifiants
  seulement.
- **Trois entrées.** Les événements de courriel du webhook d'un fournisseur, par les composants Webhook et RemoteEvent
  de Symfony ; un message brut que le serveur de courriel envoie à une commande de console ; la boîte des rebonds, par le
  port `BounceMailbox`.

## État

| Partie | État |
|---|---|
| `PhilEmailAddressBundle` et sa configuration `phil_email_address` | Implémenté et testé |
| `DeliverableEmailAddress` sur le conteneur, `AcceptedEmailAddress` et les politiques par site et par liste | Implémenté et testé |
| `EmailAddressType`, la route `phil_email_address_domains` et le contrôleur Stimulus | Implémenté et testé |
| `EmailAddresses` : forme normalisée, empreinte, adresses de rôle | Implémenté et testé |
| `BounceRules`, l'événement `BounceDecided` et `BounceDecidedMessage` | Implémenté et testé |
| Le consommateur de webhook et la commande `phil:email-address:read-bounce` | Implémenté et testé |
| Le port `BounceMailbox`, son adaptateur Maildir et la commande `phil:email-address:poll-mailbox` | Implémenté et testé |
| La lecture d'une boîte IMAP | Pas dans ce bundle : fetchmail, getmail ou la livraison locale du serveur de courriel remplit le Maildir ; `BounceMailbox` laisse la place à un paquet IMAP distinct |
| La recette Flex, dans `recipe/0.1` | Préparée |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les routes et le contrôleur Stimulus. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple : politiques, DNS, suggestions, rebonds. |
| [Rebonds](doc/fr/bounces.md) | Les règles de rebond, leurs trois entrées, l'événement et le message. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les événements à écouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, en PHP et en JavaScript. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\EmailAddressBundle\PhilEmailAddressBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_email_address.yaml
phil_email_address:
    lists:
        staff: { allowed_domains: [example.org] }
    suggestions:
        url: /email-address/domains.json
```

```php
use Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType;

$builder->add('email', EmailAddressType::class, ['list' => 'staff']);
```

```php
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class SuspendBouncedAddresses
{
    public function __invoke(BounceDecided $event): void
    {
        if (BounceDecision::Suspend === $event->decision) {
            // Stop sending to $event->notice->address, or to the recipient $event->notice->recipientId.
        }
    }
}
```

## Prérequis

PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente. Le champ de formulaire exige
`symfony/form`, les commandes `symfony/console`, le consommateur de webhook `symfony/remote-event`, la transmission des
décisions `symfony/messenger`, et la route `symfony/routing` ; le bundle n'enregistre chaque partie que lorsque son
paquet est installé.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
