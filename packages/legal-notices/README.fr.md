<!-- phil-dev source-sha256=9f4bffd73604f7504200a3c0dcb080bca6d4d42d02189e93ae749c8cdd45b7dc -->
# phil/legal-notices

**Les mentions légales et la politique de confidentialité d'un site, sous forme de documents versionnés dans chaque
locale.**

phil/legal-notices modélise les deux documents qu'un site publie à son propre sujet : ses mentions légales, qui disent
qui publie le site, qui dirige la publication et qui l'héberge, et sa politique de confidentialité. Un
`LegalDocumentVersion` est une version d'un document : son numéro, son corps dans chaque locale et le format de ce
corps, le moment de son entrée en vigueur, son état de publication (brouillon, publié, retiré) et un court résumé de ce
qui a changé. Un `DocumentHistory` contient toutes les versions d'un document. Il trouve celle qui est en vigueur à un
moment donné, la prochaine à entrer en vigueur et la liste que voit le public.

Un `SiteIdentity` contient les champs que nomment les documents : l'éditeur, son adresse et son immatriculation, le
directeur de la publication, le contact, l'hébergeur, le responsable de la vie privée. Il indique aussi les champs
qu'exige chaque document. Un corps nomme ces champs par des paramètres substituables, `${publisher_name}`, ainsi que
les durées de conservation, `${retention.R-001}`, et les catégories de consentement, `${consent.analytics}`, que le
site fournit par deux ports. `LegalNotices` relie le tout : il lit les versions depuis un `DocumentSource`, produit le
rendu d'un corps par un `BodyRenderer`, remplit ses paramètres, liste les versions publiées dans un intervalle de temps
et vérifie ce qui manque à chaque document public.

Il s'adresse à tout site PHP qui doit publier des mentions légales et une politique de confidentialité, conserver chaque
version publiée et afficher les durées et les témoins de connexion promis par sa politique à partir des données mêmes
que son code applique. Chaque valeur est vérifiée là où elle est construite : une locale, une adresse de courriel, une
durée, un nom de témoin, l'ordre des dates d'une version. Chaque valeur insérée dans un corps l'est dans son texte seulement, échappée pour le HTML, jamais dans une balise.
Dans une application Symfony, phil/legal-notices-bundle sert les documents à `/legal` et `/privacy`, écrit leurs
versions dans la configuration et lit les durées de conservation de phil/privacy-bundle.

C'est une simple bibliothèque PHP, sans autre dépendance que les extensions filter et mbstring. Ses ports sont dans
`Contract` : `DocumentSource`, `BodyRenderer`, `RetentionDurations` et `ConsentCategories`.

## État

| Partie | État |
|---|---|
| `DocumentType`, `Field`, `PublicationState`, `SiteIdentity` et les champs qu'exige chaque document | Implémenté et testé |
| `LegalDocumentVersion`, `DocumentHistory` : les versions, celle en vigueur, la prochaine, la liste publique | Implémenté et testé |
| `RetentionDuration`, `RetentionKind`, `DurationWords` : les durées en toutes lettres en anglais et en français | Implémenté et testé |
| `ConsentCategory` | Implémenté et testé |
| `Placeholders`, `PlainTextBody`, `LegalNotices`, `Problem`, `DocumentPublished` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Référence](doc/fr/reference.md) | Les types, leurs règles, les versions et leurs états, la vérification. |
| [Paramètres substituables](doc/fr/placeholders.md) | Les paramètres d'un corps, leurs valeurs et leur remplissage. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée, et ses cibles de fuzzing. |

## Démarrage rapide

```php
$notices = new LegalNotices(
    $source,                        // a DocumentSource
    new PlainTextBody(),
    new SiteIdentity([
        'publisher_name' => 'Example Inc.',
        'contact_email' => 'hello@example.com',
        'dpo_name' => 'Jo Smith',
        'dpo_contact' => 'privacy@example.com',
    ]),
);

$version = $notices->inForce(DocumentType::PrivacyPolicy, new DateTimeImmutable());
$html = null === $version ? null : $notices->render($version, 'fr_CA');

foreach ($notices->check() as $problem) {
    echo $problem->code, ': ', $problem->message, "\n";
}
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions filter et mbstring.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
