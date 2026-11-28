<!-- phil-dev source-sha256=38e487cfd94a00c5ad0bbceaee86deae2267a109c872bbbcebd7621255cb1414 -->
# phil/email-preview

**Repérez ce qui casse un courriel avant son envoi : une partie texte absente, aucun désabonnement en un clic, des pixels
espions, des styles oubliés, un courriel trop large ou trop long.**

phil/email-preview est une bibliothèque PHP simple. Elle vérifie un courriel sans l'envoyer — chaque aperçu d'un gabarit
rendu par phil/email-templates-bundle, ou tout courriel construit pour Symfony Mailer — selon un ensemble de règles, et
donne un rapport à afficher à côté de l'aperçu, en texte, en fragment HTML ou en JSON.

Le HTML est lu par l'analyseur HTML5 de PHP (`Dom\HTMLDocument`), comme le lit un navigateur, quelle que soit sa forme.

## Les règles par défaut

| Règle | Vérifie | Résultat |
|---|---|---|
| `text-part` | Le courriel a une partie en texte brut, et elle donne l'adresse de chaque lien `http:`, `https:` et `mailto:` du HTML. | Erreur |
| `one-click-unsubscribe` | Un courriel envoyé en masse a `List-Unsubscribe` avec une adresse `https:` et `List-Unsubscribe-Post: List-Unsubscribe=One-Click` (RFC 8058). | Erreur |
| `tracking-pixel` | Aucune image distante d'un pixel ou moins, par ses attributs ou son style, et aucune adresse d'image qui porte une valeur du destinataire. | Erreur |
| `no-style-element` | Aucun élément `<style>` ne reste : les styles sont intégrés aux éléments. | Erreur |
| `max-width` | Aucun élément n'est plus large que 600 pixels, par son attribut `width` ou par le `width`, le `min-width` ou le `max-width` de son style. | Erreur |
| `subject-length` | L'objet compte au plus 50 caractères, comptés comme les voit un lecteur. | Avertissement |

Une application ajoute ses propres règles, ou remplace celles-ci, par l'interface `Rule`.

## État

| Partie | État |
|---|---|
| Les six règles par défaut et l'interface `Rule` | Implémenté |
| Le rapport, en texte, en fragment HTML échappé et en JSON | Implémenté |
| La vérification de chaque aperçu d'un gabarit de phil/email-templates-bundle | Implémenté |
| La vérification d'un `Symfony\Component\Mime\Email` | Implémenté |

L'aperçu lui-même — le courriel dessiné dans un cadre isolé — relève de l'administration de phil/email-templates-bundle ;
cette bibliothèque ajoute la vérification à côté.

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la vérification d'un courriel et l'affichage du rapport. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |
| [Points d'extension](doc/fr/extension-points.md) | L'écriture d'une règle, et ce qui est stable et quand. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et son banc d'essai. |

## Démarrage rapide

```sh
composer require phil/email-preview
```

```php
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Sending;

$report = new EmailChecker()->check(CheckedEmail::fromRendered($renderedEmail, Sending::bulk($headers)));

if (!$report->passes()) {
    echo $report->toText();
}
```

Chaque aperçu d'un gabarit, chacun vérifié :

```php
use Phil\EmailPreview\Preview\PreviewChecks;

foreach (new PreviewChecks($previewRenderer)->check($key, $site, $locale) as $preview) {
    echo $preview->report->toHtml();
}
```

## Prérequis

PHP 8.5 ou une version plus récente, avec les extensions `dom`, `intl`, `libxml` et `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
