<!-- phil-dev source-sha256=c20dc16fd1e799bf789f8d78e576ec26b5b48fda7f8ba4a0e008436bb073105b -->
# Référence

Chaque type public du paquet, avec son rôle.

## Le bundle

| Type | Rôle |
|---|---|
| `Phil\EmailAddressBundle\PhilEmailAddressBundle` | Le bundle : sa configuration `phil_email_address` et ses services. |

## Adresses et politiques

| Type | Rôle |
|---|---|
| `Phil\EmailAddressBundle\Application\EmailAddresses` | La forme normalisée d'une adresse, la comparaison de deux adresses, l'empreinte, et si une adresse désigne un rôle. |
| `Phil\EmailAddressBundle\Application\EmailPolicy` | Ce qu'un formulaire accepte d'une adresse ; superposée à une autre politique par `over()`, transformée en contrainte de la bibliothèque par `constraint()`. |
| `Phil\EmailAddressBundle\Application\EmailPolicies` | La politique en vigueur : celle du bundle, celle du site courant et celle d'une liste, superposées. |
| `Phil\EmailAddressBundle\Application\Contract\CurrentSite` | Le site auquel appartient la requête courante, dont la clé choisit la politique du site. |
| `Phil\EmailAddressBundle\Application\Exception\UnknownEmailList` | Un formulaire ou une contrainte nomme une liste que la configuration ne contient pas. |
| `Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint` | Une empreinte à clé de la forme normalisée d'une adresse, portée à la place de l'adresse. |

## Formulaires, validation et HTTP

| Type | Rôle |
|---|---|
| `Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress` | La contrainte d'une adresse que la politique en vigueur accepte ; option `list`. |
| `Phil\EmailAddressBundle\Infrastructure\Validator\AcceptedEmailAddressValidator` | La vérifie par le `DeliverableEmailAddress` de la bibliothèque, dans le contexte courant. |
| `Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType` | Le champ de courriel : la contrainte, le contrôleur Stimulus, la suggestion ou l'avertissement comme aide une fois soumis. Options `list`, `accepted`, `suggest`. |
| `Phil\EmailAddressBundle\Infrastructure\Http\EmailDomainsController` | La route `phil_email_address_domains` : les domaines connus, les fautes de domaine de premier niveau et les adresses de rôle, en JSON. |
| `Phil\EmailAddressBundle\Infrastructure\Dns\CachedMailDomainResolver` | Garde dans un groupe de cache les réponses d'un autre `MailDomainResolver`. |

## Rebonds

| Type | Rôle |
|---|---|
| `Phil\EmailAddressBundle\Domain\NoticeKind` | Ce que dit un avis : `Hard`, `Soft`, `Complaint`, `Unsubscribe`, `Review`. |
| `Phil\EmailAddressBundle\Domain\NoticeSource` | D'où il vient : `Message`, `Mailbox`, `Webhook`. |
| `Phil\EmailAddressBundle\Domain\BounceDecision` | Ce que les règles ont décidé : `Suspend`, `Count`, `Unsubscribe`, `Review`, `Duplicate`. |
| `Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice` | Une adresse dont parle un rebond, son genre, son rapport, son authentification et son identifiant de destinataire. |
| `Phil\EmailAddressBundle\Domain\Event\BounceDecided` | L'événement d'une décision, avec son avis et les rebonds temporaires comptés. |
| `Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository` | Ce dont les règles se souviennent : les avis décidés et les rebonds temporaires. |
| `Phil\EmailAddressBundle\Application\Bounces\BounceRules` | Décide un avis et répartit l'événement. |
| `Phil\EmailAddressBundle\Application\Bounces\BounceNotices` | Transforme un rapport de l'analyseur de rebonds, ou un événement de webhook, en avis. |
| `Phil\EmailAddressBundle\Application\Bounces\BounceIngestor` | Analyse un message brut et fait décider ses avis par les règles. |
| `Phil\EmailAddressBundle\Application\Bounces\MailboxPoller` | Lit la boîte des rebonds, absorbe chaque message et l'acquitte. |
| `Phil\EmailAddressBundle\Application\Contract\BounceMailbox` | Le port de la boîte des rebonds. |
| `Phil\EmailAddressBundle\Application\MailboxMessage` | Un message de la boîte : son identifiant et son texte brut. |
| `Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage` | Une décision transmise par Messenger, avec des identifiants seulement. |
| `Phil\EmailAddressBundle\Infrastructure\Cache\CacheBounceHistoryRepository` | L'historique dans un groupe de cache, chaque clé étant une empreinte. |
| `Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository` | L'historique en mémoire, pour les tests. |
| `Phil\EmailAddressBundle\Infrastructure\Mailbox\MaildirBounceMailbox` | La boîte des rebonds sous forme de Maildir. |
| `Phil\EmailAddressBundle\Infrastructure\RemoteEvent\MailerEventConsumer` | Consomme les événements de courriel du webhook d'un fournisseur. |
| `Phil\EmailAddressBundle\Infrastructure\Messenger\ForwardBounceDecision` | Transmet chaque décision sous forme de `BounceDecidedMessage`. |
| `Phil\EmailAddressBundle\Infrastructure\Console\ReadBounceCommand` | `phil:email-address:read-bounce [file]` : un message brut lu dans un fichier ou sur l'entrée standard. |
| `Phil\EmailAddressBundle\Infrastructure\Console\PollMailboxCommand` | `phil:email-address:poll-mailbox [--limit=50]` : la boîte des rebonds. |

## Services

| Service | Classe |
|---|---|
| `Phil\EmailAddress\Validator\DeliverableEmailAddressValidator` | Le validateur de la bibliothèque, avec les listes, le résolveur et le suggéreur du conteneur. |
| `Phil\EmailAddress\Contract\MailDomainResolver` | `CachedMailDomainResolver` au-dessus de `DnsGetRecordResolver`, ou le résolveur configuré. |
| `Phil\EmailAddress\TypoSuggester` | Le suggéreur, avec les domaines configurés. |
| `phil_email_address.disposable_domains`, `phil_email_address.top_level_domains` | Les listes de la bibliothèque, avec les domaines jetables configurés. |
| `Phil\BounceParser\BounceParser` | L'analyseur, avec le chemin de retour et l'authentificateur configurés. |
| `Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository` | `CacheBounceHistoryRepository` au-dessus de `bounces.history`. |

## Le contrôleur Stimulus

`phil--email-address-bundle--email-address`, sur le champ. Valeurs : `url`, l'URL de la route ; `suggestion`, le message
avec `%address%` ; `use`, le libellé du bouton qui prend la suggestion ; `role`, l'avertissement. Action : `check`, sur
`blur`. Événements : `hint` avec ce qu'il a trouvé — `{kind: 'suggestion', address}`, `{kind: 'role'}` ou `null` —,
`applied` avec l'adresse prise, `failed` avec l'erreur quand les listes ne peuvent pas être lues.
