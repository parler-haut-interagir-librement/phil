<!-- phil-dev source-sha256=238d44c7f506826415153870aca5e72c2af784dae21649f0542d0f5e5139a5e9 -->
# Campagnes

Une `Campaign` est un message à une liste, ou à un segment de celle-ci : un sujet, un préentête — la ligne que les
clients de courriel montrent après le sujet —, un corps en Markdown, le gabarit qui le met en page, le profil
d'expéditeur avec lequel il part, et la façon dont ses clics sont comptés. Elle se modifie tant qu'elle est un
brouillon, est planifiée ou envoyée tout de suite, et est envoyée une seule fois.

```php
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Domain\Model\Campaign;

$campaign = new Campaign($id, $site, $list->id, 'November news', $markdown, preheader: 'Three new games');
$campaignRepository->save($campaign);

$campaigns->sendNow($campaign);
$campaigns->schedule($campaign, new DateTimeImmutable('2026-11-20 09:00'));
$campaigns->cancel($campaign);
$campaigns->results($campaign->id);   // deliveries by state, clicks by link
```

## Le corps et sa mise en page

Le corps est du Markdown, compilé et assaini par `phil/markup` à son niveau `all` : aucun script, aucun attribut
d'événement, rien qu'un message ne doive contenir. Il est ensuite mis en page :

- par la mise en page de la maison, `@PhilNewsletter/email/layout.html.twig` : une colonne, lisible sans images, ses
  styles en ligne, les liens vers le centre de préférences et de désinscription au bas ;
- ou par un gabarit que nomme la campagne, écrit par un administrateur ou un membre, produit dans le bac à sable.

Une mise en page lit `subject`, `preheader`, `content` — le corps, déjà assaini —, `list_name`, `site`,
`unsubscribe_url` et `preferences_url`. La partie texte du message est tirée de son HTML.

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

Envoyer une campagne envoie un message `SendCampaign`. Son *handler* demande les destinataires au `SegmentResolver` —
chaque abonné de la liste qui peut recevoir, lu par pages de 500 — et crée un envoi par destinataire, dont
l'identifiant est tiré de la campagne et de l'abonné, de sorte que traiter le message de nouveau ne met rien en file
deux fois ; chaque nouvel envoi reçoit un message `SendDelivery`. La planification `newsletter` démarre les campagnes
planifiées chaque minute.

Chaque `SendDelivery` vérifie de nouveau l'abonné : celui qui a quitté la liste, s'est mis en pause, a rebondi ou s'est
plaint entre-temps est ignoré, tout comme tout le monde quand la campagne a été annulée. Hors de la fenêtre d'envoi du
profil, le message est renvoyé avec un délai jusqu'à la prochaine ouverture ; au-delà du rythme du profil pour le
domaine du destinataire, ou de son plafond quotidien, après l'attente que donne le limiteur de débit. Un échec de
transport laisse l'envoi à réessayer et est levé, pour les nouvelles tentatives de Messenger. Un abonné en pause dont la
pause est terminée reçoit de nouveau.

## Les profils d'expéditeur

Un profil est l'origine d'un message et sa façon de partir : l'expéditeur, l'adresse de réponse, l'unique transport de
Mailer qu'il utilise — choisi avec l'en-tête `X-Transport` ; il n'y a aucune rotation entre les transports —, sa fenêtre
d'envoi, son plafond quotidien et son rythme par domaine de destinataire, compté pour chaque domaine à part sur le
limiteur de débit de Symfony, dans un groupe de cache que les *workers* partagent. Une liste nomme son profil ; une
campagne peut en nommer un autre ; un gabarit envoyé seul part avec le profil par défaut.

Chaque message porte l'expéditeur du profil, `List-Unsubscribe` et `List-Unsubscribe-Post`, `List-Id`,
`Precedence: bulk` pour une campagne, l'identifiant de l'envoi comme métadonnée de Mailer, que le webhook d'un
fournisseur renvoie, et, avec `bounces.verp`, un chemin de retour qui nomme l'envoi.
