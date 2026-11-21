<!-- phil-dev source-sha256=b1bcb9dc56b64014f2b5ac7c3007e23e8f8038e17a924234c5f2571fb4e3e77b -->
# Envoi du courriel

`ContactMailer` envoie un `TemplatedEmail` :

| Partie | Contenu | Pourquoi |
|---|---|---|
| `From` | Le `sender` | L'adresse d'un visiteur dans `From` échoue aux vérifications SPF et DMARC du domaine, et le message aboutit dans les pourriels |
| `To` | Les `Recipients` du message | |
| `Reply-To` | Le visiteur, avec son nom | Répondre, c'est lui répondre |
| `Subject` | Le `subject_prefix`, puis le sujet ou le nom du visiteur | |
| `X-Auto-Response-Suppress` | `OOF, AutoReply` | Les réponses d'absence ne sont pas envoyées au formulaire |

Les sauts de ligne du nom et du sujet sont remplacés par des espaces : ce qu'un robot soumet n'ajoute aucun en-tête. Le
corps est `@PhilContactForm/email/message.html.twig` et `message.txt.twig`, qui reçoivent `contact`, dans la langue de
la requête.

Rien n'est envoyé au visiteur : une copie permettrait à n'importe qui d'envoyer un texte de son choix à n'importe quelle
adresse par l'intermédiaire du site.

## Envoyer par Messenger

`ContactMailer` envoie par les transports de Symfony Mailer (`mailer.transports`), jamais par `MailerInterface` : le
courriel est produit et envoyé durant la requête, et le refus d'un transport est connu aussitôt, ce à quoi la page
répond par un `503` avec le formulaire encore rempli. Une application qui dirige `SendEmailMessage` vers un transport
Messenger asynchrone ne met quand même rien d'un message en file d'attente : par `MailerInterface`, le message mis en
file contiendrait le courriel non produit, avec le nom, l'adresse et le message du visiteur dans son contexte, gardé par
le transport et par son transport d'échecs jusqu'à ce que quelqu'un le relance ou le retire.

Ce qui reste à l'application : la configuration propre du service d'envoi — DSN, `X-Transport`, `envelope`, `headers` —,
que les transports appliquent comme à n'importe quel courriel, et les courriels qu'elle envoie elle-même. Un contrôleur
de l'application qui appelle `ContactMailer::send()` attrape `TransportExceptionInterface`, comme le fait la page du
bundle.
