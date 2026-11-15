<!-- phil-dev source-sha256=bbfd07441b4332baca7e010f02ac44a99c45b1bad50de89e179a700d6234b3b7 -->
# Abonnements

## Les listes et les abonnés

Une `MailingList` appartient à un site et a une clé, utilisée dans l'adresse du formulaire d'abonnement, un nom, une
indication de double confirmation, et son profil d'expéditeur. Un `Subscriber` est une personne sur un site : son
adresse, l'identifiant par lequel l'application la connaît quand elle en donne un — l'identifiant externe —, et son
état. Sa place sur chaque liste est une `Membership`.

| `SubscriberStatus` | Signifie |
|---|---|
| `pending` | Attend la confirmation d'une double confirmation |
| `active` | Reçoit ce qu'envoient ses listes |
| `paused` | Ne reçoit rien jusqu'à la fin de la pause, puis reçoit de nouveau sans qu'on le lui demande |
| `unsubscribed` | A quitté chaque liste |
| `bounced` | L'adresse ne reçoit plus de courriel : plus jamais écrite |
| `complained` | A signalé un message comme pourriel : plus jamais écrite |

```php
use Phil\NewsletterBundle\Application\Subscriptions;

$outcome = $subscriptions->subscribe($list, 'ada@example.com', externalId: $user->getId());
$subscriptions->confirm($subscriberId, $list->id);
$subscriptions->join($subscriberId, $otherList);
$subscriptions->leave($subscriberId, $list->id, UnsubscribeReason::TooFrequent);
$subscriptions->pause($subscriberId, new DateTimeImmutable('+4 weeks'));
$subscriptions->resume($subscriberId);
```

`subscribe()` valide l'adresse avec `phil/email-address` — sa syntaxe, son domaine de premier niveau et les domaines
jetables — et lève `SubscriptionRefused` avec les problèmes et une suggestion en cas de faute de frappe. Il
n'interroge jamais le DNS : la requête qui abonne n'attend pas un résolveur. Une adresse qui a rebondi ou s'est plainte
n'est abonnée à rien et ne reçoit rien. Une liste à double confirmation garde l'abonnement en attente et envoie la
confirmation par Messenger ; son lien signé est valide sept jours. Chaque changement est sauvegardé, puis annoncé par
un événement `AudienceChanged`.

## La vérification du domaine dans le worker

Avec `check_mail_domain: true`, la valeur par défaut, le worker qui gère `SendConfirmation` demande au DNS si le domaine
de l'adresse reçoit du courriel avant d'envoyer la confirmation :

| Ce que dit le DNS | Ce qui se passe |
|---|---|
| Des enregistrements MX, ou un enregistrement A ou AAAA | La confirmation est envoyée. |
| Aucun enregistrement MX, A ni AAAA, ou un MX nul (RFC 7505) | Aucune confirmation ; l'abonnement à la liste devient `refused`, et un avis est journalisé. |
| La recherche échoue ou expire | La confirmation est envoyée : un échec ne dit rien du domaine. |

L'avis nomme l'abonné et la liste par leurs identifiants et la réponse du DNS (`no_record` ou `null_mx`), jamais
l'adresse, comme chaque message et chaque ligne de journal du bundle. Un abonnement refusé n'est pas annoncé. S'abonner
de nouveau commence une nouvelle confirmation, et une nouvelle vérification : un domaine dont les serveurs de courriel
manquaient pendant un temps est accepté dès qu'ils répondent. Une adresse à littéral IP n'est pas vérifiée.

Le message lui-même ne porte que les identifiants de l'abonné et de la liste ; ce dont le worker a besoin est lu dans le
stockage quand il le gère.

## Les pages

| Page | Ce qu'elle fait |
|---|---|
| Abonnement | Un formulaire avec l'adresse, un jeton CSRF et un champ caché que les robots remplissent. Il donne la même réponse quoi qu'il soit advenu de l'adresse, pour que personne n'y apprenne qui est abonné. |
| Confirmation | Un bouton qui confirme ; suivre le lien seul ne confirme rien, puisque les analyseurs de courriel suivent les liens. |
| Désinscription | Un choix entre la liste du message et toutes les listes, et une raison facultative. |
| Centre de préférences | Les listes du site à cocher, une pause d'une, de quatre ou de douze semaines, le retour, le départ de tout. |

Les pages sont des gabarits Twig sous `@PhilNewsletter/pages/`, qui étendent `@PhilNewsletter/pages/layout.html.twig` ;
remplacez cette mise en page dans `templates/bundles/PhilNewsletterBundle/pages/layout.html.twig` pour porter celle du
site. Leurs textes sont dans le domaine de traduction `phil_newsletter`, en anglais et en français. Un lien qui n'est
pas celui de l'infolettre, ou qui a expiré, reçoit un `404`. Les pages ne sont jamais mises en cache, jamais indexées et
n'envoient aucun référent.

## La désinscription en un clic

Chaque message d'une campagne porte `List-Unsubscribe` et `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, comme le
demande la RFC 8058 : le bouton de désinscription d'un client de courriel envoie un `POST` avec
`List-Unsubscribe=One-Click` au lien signé, qui retire l'abonné de la liste du message et répond `200` avec un corps
vide. Le lien n'expire pas. Un abonné sans aucune liste est désinscrit.

## Les raisons de partir

Un abonné qui part peut dire pourquoi : trop de messages, sans intérêt, jamais inscrit, une autre raison. La raison est
comptée par site, liste et jour, sans rien qui nomme la personne qui l'a donnée.

```php
$engagement->unsubscribeReasons($site, new DateTimeImmutable('-30 days'));   // ['too_frequent' => 12, ...]
```

## Les sites

Un site autonome a un seul site, `default`, ou celui que nomme `site`. Une application qui en sert plusieurs remplace le
service `CurrentSite` par un service qui lit le site de la requête ; les listes, les abonnés et les campagnes sont alors
séparés par site, et un gabarit sans site est partagé par tous. La même adresse sur deux sites fait deux abonnés.
