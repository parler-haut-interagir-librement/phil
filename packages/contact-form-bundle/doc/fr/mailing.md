<!-- phil-dev source-sha256=179df3c5c2967a3f9e81c63670494462520c01b29a1a7d63200fc45d0da0ac28 -->
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

Lorsque l'application dirige `SendEmailMessage` vers un transport Messenger asynchrone, `ContactMailer::send()` ne fait
que mettre le courriel en file d'attente. La page se redirige alors comme si le message était parti : un transport qui
le refuse échoue dans le *worker*, qui fait une nouvelle tentative et journalise l'échec, et la réponse `503` avec le
formulaire encore rempli ne se produit jamais.

Le courriel en file d'attente contient le nom, l'adresse et le message du visiteur. Ils restent dans le transport
Messenger jusqu'à ce que le *worker* les envoie et, s'il est configuré, dans son transport d'échecs jusqu'à ce que
quelqu'un les relance ou les retire. Le bundle ne conserve rien lui-même, mais une telle application y conserve bel et
bien des renseignements personnels : il lui faut une règle de conservation pour son transport d'échecs —
`messenger:failed:show` pour consulter, `messenger:failed:remove` pour purger — et elle devrait l'indiquer dans sa
politique de vie privée.
