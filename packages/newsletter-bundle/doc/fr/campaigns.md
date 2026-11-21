<!-- phil-dev source-sha256=827bd992329f1a2d1849d0f452063240cbd07ca33e6fc27669a1253b6b0fb348 -->
# Campagnes

Une `Campaign` est un message à une liste, ou à un segment de celle-ci : un sujet, un préentête — la ligne que les
clients de courriel montrent après le sujet —, un corps en Markdown, le gabarit qui le met en page, le profil
d'expéditeur avec lequel il part, la façon dont ses clics sont comptés, et son auteur. Elle se modifie tant qu'elle est
un brouillon, est approuvée, planifiée ou envoyée tout de suite, et est envoyée une seule fois.

```php
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Domain\Model\Campaign;

$campaign = new Campaign($id, $site, $list->id, 'November news', $markdown, preheader: 'Three new games', author: $user->getUserIdentifier());
$campaignRepository->save($campaign);

$campaigns->approve($campaign);       // by the signed-in user of the approving role
$campaigns->sendNow($campaign);
$campaigns->schedule($campaign, new DateTimeImmutable('2026-11-20 09:00'));
$campaigns->cancel($campaign);
$campaigns->results($campaign->id);   // deliveries by state, clicks by link
```

## Approbation

Avec `approval.required`, activé par défaut, une campagne part une fois approuvée : `sendNow()` refuse avec
`InvalidNewsletterValue` une campagne qui attend son approbation, et une campagne planifiée dont l'heure est venue
attend, planifiée, d'être approuvée, puis démarre à la minute suivante de la planification.

`Campaigns::approve()` enregistre qui a approuvé la campagne et quand, sous la forme d'un `CampaignApproval` :
l'identifiant d'utilisateur de l'utilisateur connecté, tel que la sécurité de Symfony le donne, et l'instant de
l'horloge. Elle lève `ApprovalRefused` quand l'utilisateur n'a pas le rôle de `approval.role`, `ROLE_NEWSLETTER_APPROVER`
par défaut, ou n'est pas connecté. Avec `approval.four_eyes`, l'approbateur doit être quelqu'un d'autre que l'auteur de
la campagne : une campagne sans auteur n'est alors jamais approuvée. `CampaignApproved` est distribué une fois
l'approbation sauvegardée.

L'approbation vaut pour la campagne telle qu'elle est. `Campaign::edit()` la retire, pour que ce qui part soit ce qui a
été approuvé ; `Campaigns::withdrawApproval()` la retire volontairement. Un brouillon ou une campagne planifiée est
approuvé ; une campagne en cours d'envoi, envoyée ou annulée ne l'est pas.

L'auteur et l'approbateur sont des identifiants d'utilisateur de l'application, d'au plus 191 caractères, gardés dans la
table de la campagne ; ils nomment des membres de l'équipe, jamais des abonnés.

## Le corps et sa mise en page

Le corps est du Markdown, compilé et assaini par `phil/markup` à son niveau `all` : aucun script, aucun attribut
d'événement, rien qu'un message ne doive contenir. Il est ensuite mis en page :

- par la mise en page de la maison, `@PhilNewsletter/email/layout.html.twig` : une colonne, lisible sans images, ses
  styles en ligne, les liens vers le centre de préférences et de désinscription au bas ;
- ou par un gabarit que nomme la campagne, écrit par un administrateur ou un membre, produit dans le bac à sable.

Une mise en page lit `subject`, `preheader`, `content` — le corps, déjà assaini —, `list_name`, `site`,
`unsubscribe_url` et `preferences_url`.

La partie texte du message est tirée de son HTML par `TextPart`, qui écrit l'adresse de chaque lien web entre chevrons
après ses mots : un lecteur de la partie texte trouve quand même les liens de désinscription, du centre de préférences
et, dans une confirmation, de confirmation. L'en-tête, les styles et ce qui est caché, le texte de préaperçu compris,
sont omis. `ContentRenderer` accepte en dernier argument un autre `HtmlToTextConverterInterface` de Mime ; il retourne
du texte décodé.

## Les gabarits des administrateurs et des membres

Un `Template` est un sujet et une source Twig, pour un site ou, sans site, partagé par tous. Il met en page des
campagnes, est envoyé seul par une automatisation, ou remplace la confirmation d'une double confirmation sous la clé
`confirmation`.

Il n'est jamais produit par le Twig de l'application. `SandboxedTemplates` le produit dans un `Twig\Sandbox\Sandbox`,
sur un environnement à part où rien n'est enregistré, sous une `SecurityPolicy` stricte :

| Permis | |
|---|---|
| Balises | `apply`, `for`, `if`, `set`, `with` |
| Filtres | `abs`, `capitalize`, `date`, `default`, `e`, `escape`, `first`, `join`, `last`, `length`, `lower`, `nl2br`, `number_format`, `replace`, `round`, `slice`, `striptags`, `title`, `trim`, `upper`, `url_encode` |
| Fonctions, méthodes, propriétés | aucune |

Le contexte contient des chaînes, des nombres, des listes et le corps assaini. L'opérateur d'intervalle `1..1000000` est
refusé quand un gabarit se compile, pour qu'aucun gabarit ne boucle plus de fois que ses données n'ont d'éléments ; la
sortie est coupée à un mégaoctet ; une source contient 64 Kio au plus. Un gabarit qui enfreint la politique lève
`InvalidNewsletterValue` ; `check()` avertit un éditeur avant la sauvegarde du gabarit. Les gabarits compilés sont gardés
pour le message suivant, jusqu'à 64, et oubliés à `reset()`.

## L'éditeur Markdown

`@PhilNewsletter/editor/markdown.html.twig` est une zone de texte avec une barre d'outils — titre, gras, italique,
lien, liste, citation — et un aperçu, par le contrôleur Stimulus `phil--newsletter-bundle--markdown-editor` :

```twig
{{ include('@PhilNewsletter/editor/markdown.html.twig', {name: 'body', value: campaign.body, preview_url: path('phil_newsletter_preview')}) }}
```

L'aperçu envoie le texte par `POST` à `phil_newsletter_preview`, qui ne répond qu'au rôle configuré, et montre le message
mis en page dans un cadre en bac à sable sans scripts, derrière un `Content-Security-Policy: sandbox`. Sans JavaScript,
la zone de texte reste le champ que le formulaire envoie.

## L'envoi

Envoyer une campagne démarre une exécution d'envoi et envoie un message `SendCampaign` qui nomme la campagne et
l'exécution. Son *handler* demande les destinataires au `SegmentResolver` — chaque abonné de la liste qui peut recevoir,
lu par pages de 500 — et crée un envoi par destinataire, sous l'exécution, dont l'identifiant est tiré de la campagne et
de l'abonné, de sorte que traiter le message de nouveau ne met rien en file deux fois et qu'aucune exécution ultérieure
de la campagne n'envoie deux fois au même destinataire ; chaque nouvel envoi reçoit un message `SendDelivery`. Une fois
qu'ils sont tous en file, l'exécution garde leur nombre. La planification `newsletter` démarre les campagnes planifiées
chaque minute.

Chaque `SendDelivery` vérifie de nouveau l'abonné : celui qui a quitté la liste, s'est mis en pause, a rebondi ou s'est
plaint entre-temps est ignoré, tout comme tout le monde quand la campagne a été annulée. Hors de la fenêtre d'envoi du
profil, le message est renvoyé avec un délai jusqu'à la prochaine ouverture ; au-delà du rythme du profil pour le
domaine du destinataire, ou de son plafond quotidien, après l'attente que donne le limiteur de débit. Un échec de
transport laisse l'envoi à réessayer et est levé, pour les nouvelles tentatives de Messenger. Un abonné en pause dont la
pause est terminée reçoit de nouveau.

## Les exécutions d'envoi et le disjoncteur

Un `SendRun` est une tentative d'envoi d'une campagne : son numéro, à partir de 1, et un identifiant tiré de la campagne
et de ce numéro ; son état, `running`, `paused`, `completed` ou `cancelled` ; combien de messages elle a mis en file ;
ce qu'il est advenu d'eux — `sent`, `failed` (chaque tentative que le transport a refusée ; un message envoyé après une
nouvelle tentative compte dans les deux), `skipped`, `bounced` et `complained` — ; et quand elle a démarré, fini la mise
en file, été mise en pause et pris fin. Chaque *worker* ajoute à ces comptes dans le stockage, en une seule instruction,
et un état ne change qu'à partir de l'état dans lequel il a été lu, pour que les *workers* d'une même exécution ne se
fassent rien perdre les uns aux autres. Les messages ne portent que des identifiants : l'envoi, la campagne,
l'exécution.

```php
use Phil\NewsletterBundle\Application\SendRuns;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;

foreach ($sendRuns->ofCampaign($campaign->id) as $run) {
    $run->status;                           // SendRunStatus
    $run->queued;
    $run->count(SendRunCounter::Sent);
    $run->pauseReason;                      // PauseReason, while it is paused
}

$sendRuns->pause($runId);                   // an operator holds it
$sendRuns->resume($runId);                  // an operator sends again what it holds
$sendRuns->cancel($runId);                  // what it had not sent is skipped
```

Une exécution est terminée une fois que chaque message qu'elle a mis en file a été envoyé ou ignoré ; `SendRunCompleted`
est distribué.

**Le disjoncteur.** Après chaque message compté, l'exécution est relue : dès qu'au moins `circuit_breaker.minimum`
messages ont été tentés depuis son démarrage ou sa dernière reprise, elle est mise en pause quand la part des messages
envoyés signalés comme pourriel dépasse `complaint_rate`, que la part de ceux qui ont rebondi dépasse `bounce_rate`, ou
que la part des tentatives que le transport a refusées dépasse `transport_failure_rate` — lus dans cet ordre, en
pourcentage. `SendRunPaused` est distribué avec le `PauseReason`, pour que l'application avertisse un opérateur. Les
rebonds et les plaintes d'un envoi viennent de la boîte des rebonds ou du webhook du fournisseur, et chacun est compté
une seule fois.

Une exécution en pause n'envoie rien : chacun de ses messages qu'un *worker* reçoit reste en file. Rien ne la reprend
sauf un opérateur : `SendRuns::resume()` remet en file ce que l'exécution retient, en file ou en échec, par pages de
500, distribue `SendRunResumed`, et le disjoncteur compte à partir de là. Il n'y a ni reprise automatique, ni montée
progressive du rythme après elle, ni essai sur une partie du public avant le reste : une application qui les veut les
construit sur ces services. `SendRuns::pause()` retient pour un opérateur une exécution en cours, avec la raison
`operator` ; `cancel()` termine une exécution en cours ou en pause et ignore ce qu'elle n'avait pas envoyé.

`circuit_breaker: false` désactive le disjoncteur ; les exécutions comptent, se terminent et répondent toujours à un
opérateur.

## Les profils d'expéditeur

Un profil est l'origine d'un message et sa façon de partir : l'expéditeur, l'adresse de réponse, l'unique transport de
Mailer qu'il utilise — choisi avec l'en-tête `X-Transport` ; il n'y a aucune rotation entre les transports —, sa fenêtre
d'envoi, son plafond quotidien et son rythme par domaine de destinataire, compté pour chaque domaine à part sur le
limiteur de débit de Symfony, dans un groupe de cache que les *workers* partagent. Une liste nomme son profil ; une
campagne peut en nommer un autre ; un gabarit envoyé seul part avec le profil par défaut.

Chaque message porte l'expéditeur du profil, `List-Unsubscribe` et `List-Unsubscribe-Post`, `List-Id`,
`Precedence: bulk` pour une campagne, l'identifiant de l'envoi comme métadonnée de Mailer, que le webhook d'un
fournisseur renvoie, et, avec `bounces.verp`, un chemin de retour qui nomme l'envoi.

## Mise à niveau vers 0.3

- **L'approbation est exigée par défaut.** Un brouillon ou une campagne planifiée de 0.2 n'est plus envoyé, ni démarré à
  son heure, tant qu'un utilisateur de `approval.role` ne l'a pas approuvé. Donnez ce rôle aux personnes qui approuvent,
  ou mettez `approval.required: false` pour envoyer comme avant.
- **Les tables gagnent une table, des colonnes et un index.** Exécutez la migration que décrit
  [Mise à niveau depuis 0.2](storage.md#mise-à-niveau-depuis-02) avant que les *workers* de 0.3 démarrent.
- `Campaigns` prend les `SendRuns` comme sixième argument, puis une `ApprovalPolicy` facultative, le vérificateur
  d'autorisation, le stockage des jetons et le répartiteur d'événements. `SendCampaignHandler` prend les `SendRuns` et
  l'indication que l'approbation est exigée ; `SendDeliveryHandler` et `BounceHandler` prennent les `SendRuns` comme
  dernier argument, facultatif. Le bundle les câble tous ; seule une application qui les construit elle-même change ses
  appels.
- `Campaign::start()` prend l'indication que l'approbation est exigée, `true` par défaut ; `Campaign::restore()` prend
  l'approbation comme dernier argument, facultatif ; les constructeurs de `Campaign` et de `Delivery` prennent l'auteur
  et l'exécution d'envoi comme derniers arguments, facultatifs.
- `SendCampaign` nomme l'exécution d'envoi comme deuxième argument, facultatif. Un message mis en file par 0.2, sans
  elle, est traité sous l'exécution de la campagne qui met encore en file, ou sous une nouvelle.
- `DeliveryRepository` déclare `countOfRun()`, `pendingOfRun()` et `skipPendingOfRun()`, et `SendRunRepository` est un
  nouveau port : un dépôt propre à l'application les implémente.
