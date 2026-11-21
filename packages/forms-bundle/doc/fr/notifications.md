<!-- phil-dev source-sha256=d3f3da1f3e0af81874ca889feded237e3f4e9cb5d8a01851450097cc9659f30f -->
# Notifications

`Submissions::submit()` conserve d'abord l'entrée, puis avertit chaque `EntryNotifier` à tour de rôle. Un notificateur
qui échoue (`NotificationFailed` venant d'un transport, ou toute autre erreur : un gabarit que l'application surcharge,
un refus du service de courriel, un notificateur de l'application) est journalisé avec l'id de l'entrée, la clé du
formulaire, la classe et le message de l'erreur, jamais les valeurs, et les autres notificateurs s'exécutent quand même.
L'entrée est conservée quoi qu'il arrive, et la page du visiteur indique qu'elle a été envoyée : rien n'est perdu et
rien n'est envoyé deux fois.

## Courriel

Avec `sender` réglé, `EmailNotifier` envoie chaque entrée aux destinataires de son formulaire :

- depuis l'adresse du site : l'adresse d'un visiteur dans `From` échouerait aux vérifications SPF et DMARC du domaine ;
- avec le premier champ de courriel dans `Reply-To`, pour que répondre réponde au visiteur ;
- jamais au visiteur, ce qui ferait d'un formulaire un relais de pourriel ;
- avec `X-Auto-Response-Suppress: OOF, AutoReply` ;
- dans la langue de l'entrée, chaque champ avec sa valeur, à partir de `@PhilForms/email/entry.html.twig` et
  `.txt.twig` ; la valeur d'un champ sensible est retenue, et un champ de courriel sensible ne reçoit jamais de réponse.

Un formulaire sans destinataire ne leur envoie aucun courriel.

### Autres notifications

Un formulaire envoie jusqu'à 20 autres courriels de chaque entrée, chacun à 1 à 50 adresses, avec son propre sujet, sous
une condition portant sur les valeurs :

```php
$form->notifyAlso([
    Notification::to(['sales@example.com'], 'Sales: {name}', ['==' => [['var' => 'department'], 'Sales']]),
    Notification::to(['support@example.com'], 'Support: {name}', ['==' => [['var' => 'department'], 'Support']]),
    Notification::to(['archive@example.com']),
]);
```

Les adresses sont celles du formulaire, jamais une adresse que le visiteur a saisie. Un sujet tient sur une ligne de 1
à 255 caractères, le titre du formulaire par défaut ; chaque `{field}` est remplacé par la valeur d'un champ de texte
sur une ligne, et un sujet ne peut nommer ni une case à cocher ni un champ sensible. Une condition lit les champs du
formulaire comme le fait la condition d'un champ ([formulaires](forms.md#questions-posées-sous-une-condition)) ; celle
qui lit un champ que l'entrée n'a pas demandé ne tient pas. Chaque courriel est tenté ; quand l'un échoue, le premier
échec est signalé une fois les autres envoyés.

### Envoi par Messenger

`EmailNotifier` envoie par les transports de Symfony Mailer (`mailer.transports`), jamais par `MailerInterface` : le
courriel est rendu et envoyé dans la requête qui a conservé l'entrée, et un refus du transport est connu aussitôt et
journalisé comme ci-dessus. Une application qui achemine `SendEmailMessage` vers un transport Messenger asynchrone ne
met donc rien d'une entrée en file : par `MailerInterface`, le message en file contiendrait le courriel non rendu avec
l'entrée, son formulaire et chaque valeur de son contexte — une valeur scellée en clair avec
`allow_unsealed_sensitive` —, gardés par le transport et par sa file des échecs jusqu'à ce que quelqu'un les retente ou
les retire.

Ce qui reste à l'application : la configuration propre du mailer — DSN, `X-Transport`, `envelope`, `headers` —, que les
transports appliquent comme pour n'importe quel courriel, et les courriels de ses propres notificateurs. Celle qui met
ses courriels en file garde des renseignements personnels dans son transport Messenger et dans sa file des échecs : il
lui faut une règle de conservation pour la file des échecs — `messenger:failed:show` pour regarder,
`messenger:failed:remove` pour purger — et un mot dans sa politique de confidentialité.

## Webhook

Avec `webhook_secret` réglé, `WebhookNotifier` publie chaque entrée vers le webhook de son formulaire sous forme de
message Standard Webhooks, signé avec `phil/standard-webhooks`, sans suivre de redirection :

```json
{
    "type": "form.entry.submitted",
    "timestamp": "2026-11-04T12:00:05.123Z",
    "data": {
        "form": "registration",
        "entry": "01a1a533-…",
        "submitted_at": "2026-11-04T12:00:00Z",
        "locale": "fr",
        "values": {"name": "Ada", "consent": true}
    }
}
```

Les valeurs des champs sensibles sont omises ; leurs noms sont alors listés sous `data.withheld`.

Le `webhook-id` est `msg_` suivi de l'id de l'entrée, pour que la déduplication d'un destinataire absorbe une deuxième
livraison. Une réponse autre que `2xx` échoue. Un formulaire sans webhook ne publie rien.

Les entrées portent des renseignements personnels : un webhook est donc une URL `https` sur un hôte public.
`Form::notify()` refuse `http`, `localhost` et les adresses des réseaux privés, de bouclage, de lien local ou réservés.
Avec `symfony/http-client`, le notificateur publie par `NoPrivateNetworkHttpClient`, le service
`phil_forms.webhook_client`, qui refuse au moment de la requête un nom qui se résout en une telle adresse.

## Les notificateurs de l'application

Un service qui implémente `EntryNotifier` reçoit l'étiquette `phil_forms.notifier` par configuration automatique et est
averti de chaque entrée : pour l'ajouter à un CRM, à une infolettre, à un ticket.
