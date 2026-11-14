<!-- phil-dev source-sha256=f316d051a3c87d4b76a063b34fe6d3718d037f4a4b34652ba2697f7da11a757a -->
# Points d'extension

Ce qu'une application implémente, étiquette, configure ou surcharge. La protection contre le pourriel vient de
`phil/spam-protection-bundle`, les webhooks signés de `phil/standard-webhooks`.

## Ports

### `EntryNotifier`

`Phil\FormsBundle\Application\EntryNotifier` : averti de chaque entrée une fois qu'elle est conservée. Un service qui
l'implémente reçoit l'étiquette `phil_forms.notifier` par configuration automatique ; `EmailNotifier` et
`WebhookNotifier` sont fournis. Voir [Notifications](notifications.md).

| Méthode | Contrat |
|---|---|
| `notify(Entry $entry): void` | Envoie l'entrée où elle doit aller (un CRM, une infolettre, un ticket) ; un échec est journalisé sans les valeurs, les autres notificateurs s'exécutent quand même et l'entrée est conservée |

### `CurrentSubject`

`Phil\FormsBundle\Application\CurrentSubject` : qui une entrée concerne, quand la personne est connue au moment de
l'envoi. `TokenCurrentSubject` nomme l'utilisateur connecté par Symfony Security ; une application dont les demandes
d'accès et d'effacement nomment les personnes autrement crée un alias du port vers son propre service. Voir [Entrées et
conservation](entries.md).

| Méthode | Contrat |
|---|---|
| `current(): ?string` | L'identifiant de la personne, ou `null` pour un visiteur |

### `ConditionEvaluator`

`Phil\FormsBundle\Domain\Contract\ConditionEvaluator` : lit les conditions des champs et des notifications.
`BasicConditions` est la valeur par défaut, avec un alias comme port ; un évaluateur à la logique plus riche prend sa
place par un alias de l'application, les conditions restant des données dont le JSON est un sous-ensemble de
JsonLogic. `JsonLogicConditions`, de `phil/form-logic`, lit tout son sous-ensemble — `and`, `or`, `!`, ordres,
`missing`, arithmétique — ; sous l'un comme l'autre, `!=` tient pour un champ laissé vide, comme dans JsonLogic. Voir
[Formulaires](forms.md#conditions-plus-riches-avec-philform-logic).

| Méthode | Contrat |
|---|---|
| `fields(array $condition): list<string>` | Les champs que lit la condition, chacun une fois ; une condition qui lit un champ non demandé ne tient jamais |
| `check(array $condition, array $fields): void` | Lève `InvalidForm` pour une condition qu'il ne lit pas, ou qui lit un champ absent de `$fields` (les champs qui le précèdent), ou qui en compare un avec des valeurs qu'il ne prend jamais |
| `holds(array $condition, array $values): bool` | Si la condition tient pour les valeurs des champs demandés ; travail borné, aucune E/S |

### `FieldSealer`

`Phil\FormsBundle\Domain\Contract\FieldSealer` : scelle les valeurs des champs sensibles. `KeyManagementFieldSealer` est
câblé quand `sealing.key` nomme une clé maîtresse, sur le client du composant KeyManagement de Symfony que nomme
`sealing.kms` ; une application crée un alias du port vers son propre service pour sceller autrement. Sans l'un ni l'autre, un
formulaire qui a un champ sensible est refusé avec `UnsealedSensitiveField`, sauf si `allow_unsealed_sensitive` est vrai.
Voir [Entrées et conservation](entries.md#valeurs-sensibles).

| Méthode | Contrat |
|---|---|
| `seal(string $value, string $context): string` | De l'ASCII imprimable, le contexte (`phil_forms.entry.{id}.{field}`) étant authentifié avec la valeur |
| `open(string $sealed, string $context): string` | Lève `UnreadableValue` quand la valeur a été altérée, déplacée, ou scellée sous une clé qui n'est plus connue |

### `ChoicePlaces`

`Phil\FormsBundle\Domain\Contract\ChoicePlaces` : les places occupées dans les choix qui ont une capacité, un alias
public de `DoctrineFormRepository`. Un stockage propre à l'application prend une place de façon atomique, jamais
au-delà de la capacité, et rend les places quand des entrées sont retirées.

| Méthode | Contrat |
|---|---|
| `take(Form $form, string $field, string $choice, int $capacity): bool` | Si une place a été prise |
| `release(Form $form, string $field, string $choice): void` | Rend une place, jamais en dessous de zéro |
| `taken(Form $form, string $field, string $choice): int` | Les places occupées |

### `FormRepository`

`Phil\FormsBundle\Domain\Repository\FormRepository` : les formulaires et les entrées, un alias public de
`DoctrineFormRepository`. Un stockage propre à l'application garde `expired()` borné par sa limite et `about()` indexé
sur le sujet et l'empreinte d'adresse, pour que la conservation et les demandes liées à la vie privée ne lisent jamais
toutes les entrées.

### `EmailDigester`

`Phil\FormsBundle\Domain\Subject\EmailDigester` : l'empreinte qu'une entrée conserve d'une adresse. `HmacEmailDigester`
est le HMAC-SHA256 de l'adresse sans espaces aux extrémités et en minuscules, sous `digest_key`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `sender`, `subject_prefix` | Le courriel d'une entrée ; sans expéditeur, pas de courriel |
| `webhook_secret` | La clé de signature du webhook ; sans elle, pas de webhook |
| `template` | La page d'un formulaire |
| `digest_key` | La clé des empreintes d'adresses ; la changer rend les entrées déjà conservées introuvables pour les demandes par adresse |
| `sealing.key`, `sealing.kms` | La clé maîtresse et le client du composant KeyManagement de Symfony qui scellent les valeurs sensibles ; sans clé, pas de scelleur |
| `allow_unsealed_sensitive` | Conserve les valeurs sensibles telles qu'envoyées quand aucun scelleur n'est configuré, au lieu de refuser le formulaire |

## Gabarits

| Gabarit | Comment une application le surcharge |
|---|---|
| `@PhilForms/form.html.twig` | `template`, ou sa propre page qui étend sa mise en page et inclut `@PhilForms/_form.html.twig` avec `form`, `definition` et `sent` |
| `@PhilForms/email/entry.html.twig`, `.txt.twig` | Surchargé comme tout gabarit de bundle |

## Services

| Service | Ce que l'application en fait |
|---|---|
| `Submissions` | `submit($form, $values, $locale, $subject, $tiers)` prend une entrée depuis le code, comme le fait une commande de billets pour chaque personne ; `fullChoices($form)` |
| `SealedValues` | `reveal($entry)` donne les valeurs d'une entrée telles qu'elles ont été envoyées, pour un écran de l'application |

## Vie privée

Avec `phil/privacy-bundle`, `FormEntriesPrivacy` répond aux demandes d'accès et d'effacement comme son module `forms`.
