<!-- phil-dev source-sha256=034a34e7d78c35e48c8fafe0e6bd2fee81b924bd3a64e29ca3581187fbf798bd -->
# Rendu et envoi

## Produire le rendu sans envoyer

`Application\Rendering\EmailRenderer` produit le rendu d'un courriel et n'envoie rien :

```php
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;

$email = $renderer->render('confirmation', $values, new RenderOptions(
    site: null,              // the current site by default
    locale: 'fr_CA',         // the default locale by default
    segments: ['first-time'],
    version: null,           // a version the site published, rather than the one it sends
));

$email->subject; $email->preheader; $email->html; $email->text; $email->embeds;
$email->locale;  // fr: the locale the template was found in
$email->origin;  // TemplateOrigin::Site, Shared or Default
$email->version; // the version, null for a default file
$email->isClippedByGmail(); // the HTML is larger than 102 KiB
```

Il trouve le gabarit ([Sites, brouillons et versions](sites-and-versions.md#quel-gabarit-est-envoye)), une version
publiée ou un fichier par défaut, jamais un brouillon, vérifie les valeurs selon son schéma — `InvalidVariables` quand elles ne correspondent pas —, produit le rendu de ses sources dans le bac à
sable, enveloppe le corps dans la mise en page de la maison, met les styles en ligne, écrit la partie en texte brut à
partir du HTML, sauf si le gabarit écrit la sienne, et lit les images intégrées. `RenderingFailed` signale une source
qui échoue à l'exécution, un composant à qui l'on donne ce qu'il refuse, une sortie qui dépasse `max_html_bytes`, ou une
image illisible ; `UnknownTemplate`, un gabarit introuvable.

`renderSamples()` produit le rendu d'un gabarit déjà trouvé avec ses valeurs d'exemple, comme le font l'aperçu et les
vérifications.

## La mise en page de la maison

La mise en page est un gabarit du Twig de l'application, de confiance et hors du bac à sable :
`@PhilEmailTemplates/email/layout.html.twig` par défaut, remplacé comme n'importe quel gabarit d'un bundle dans
`templates/bundles/PhilEmailTemplatesBundle/email/layout.html.twig`, ou un autre nommé par `layout`. Elle reçoit
`subject`, `preheader`, `body` (HTML), `email` (clé, site, locale, segments), `theme` (`light`, `dark`, `font`) et
`brand` (`name`, `logo`).

La mise en page par défaut dessine une colonne centrée de 600 pixels sur la couleur de fond, qui rétrécit sur un petit
écran ; d'abord le pré-en-tête, caché, suivi de caractères d'espacement pour que la boîte de réception ne montre rien du
corps après lui, et marqué `data-text="skip"` pour que le texte brut l'omette ; puis la marque, un nom ou un logo ; puis
le corps.

## Le mode sombre

La mise en page déclare `color-scheme: light dark` et `supported-color-schemes`, dans son `<meta>` et dans sa feuille de
style, et redéfinit le style du fond, de la carte, du texte, des liens et de chaque composant dans un bloc
`@media (prefers-color-scheme: dark)`, avec les couleurs de `theme.dark`. Les clients qui l'appliquent — Apple Mail, iOS
Mail, Outlook pour macOS et quelques autres — montrent le thème sombre ; ceux qui ne l'appliquent pas, dont le client
Web de Gmail, gardent le thème clair ou l'inversent d'eux-mêmes. Les règles du mode sombre restent dans l'élément
`<style>`, puisqu'elles ne peuvent pas être mises en ligne.

## Les styles en ligne

Avec `twig/cssinliner-extra`, son filtre `inline_css` copie chaque règle de l'élément `<style>` de la mise en page sur
les éléments qu'elle désigne, une déclaration déjà en ligne l'emportant, pour les clients qui ne lisent aucune feuille de
style ; les requêtes média restent dans l'élément `<style>`, qui est conservé. Sans lui, `NoStyleInliner` laisse le
document tel quel : les composants écrivent déjà leurs styles en ligne.

## La partie en texte brut

`HtmlToText` écrit la partie en texte brut à partir du document entier, lu par l'analyseur HTML5 de PHP comme le lit un
navigateur : paragraphes et titres séparés par une ligne vide, un saut de ligne conservé, un lien suivi de son adresse
entre crochets, un élément de liste précédé d'un tiret ou de son numéro, les cellules d'une table de données séparées
par `|`, celles d'une table de mise en page — `role="presentation"` — chacune sur sa ligne, une image par son texte
de remplacement, un filet par `----`. L'en-tête, les styles, les scripts et chaque élément marqué `hidden` ou
`data-text="skip"` sont omis. Un gabarit écrit la sienne dans `text`.

## Les images intégrées

Les images qu'un gabarit déclare dans `embeds` sont lues par l'`EmbedLoader`, par défaut `DirectoryEmbedLoader` sous
`embeds.directory` : un chemin qui sort du répertoire, par `..` ou par un lien symbolique, est refusé, de même qu'un
fichier plus grand que `embeds.max_bytes` ou qui n'est pas un PNG, un JPEG ou un GIF, reconnu à ses premiers octets.
Chacune devient une `EmbeddedImage` jointe en ligne sous son identifiant de contenu, si bien que `cid:logo` dans le HTML
l'affiche.

## Un Email prêt à envoyer

`Application\EmailComposer` écrit un courriel produit dans un `Email` de Symfony Mailer — objet, HTML, texte, images
jointes en ligne — et lui donne l'expéditeur `from` configuré quand il n'en a pas :

```php
$mailer->send($composer->compose('ticket', $values, new RenderOptions(locale: 'fr'))->to($address));
$composer->compose('ticket', $values, email: new Email()->from('box-office@example.com'));
EmailComposer::toEmail($renderedEmail);
```

Un `TemplatedEmail` n'est pas nécessaire : le corps est déjà produit, et le moteur de rendu de corps de Mailer laisse un
tel `Email` tel quel.

## Envoyer depuis un message

`Infrastructure\Messenger\SendTemplatedEmail` porte une clé de gabarit, une référence — une commande, un billet, une
inscription — et, facultativement, un site : des identifiants seulement, pour qu'aucune adresse et aucune valeur
n'attende dans une file. Quand il est traité, le `DeliveryResolver` de l'application retourne le `Delivery` — les
destinataires, les valeurs, la locale, les segments, le site — et le courriel est composé puis envoyé par Mailer ; une
référence pour laquelle il retourne `null` est abandonnée.

```php
final class Deliveries implements DeliveryResolver
{
    public function resolve(string $template, string $reference): ?Delivery
    {
        $order = $this->orders->find($reference);

        return null === $order ? null : new Delivery([new Address($order->email)], ['order' => $order->toTemplate()], $order->locale);
    }
}
```

```yaml
phil_email_templates:
    messenger: { delivery_resolver: App\Mail\Deliveries }
```

```php
$bus->dispatch(new SendTemplatedEmail('order.confirmation', $order->id));
```

Acheminez le message vers un transport asynchrone comme n'importe quel autre.
