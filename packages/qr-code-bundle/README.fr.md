<!-- phil-dev source-sha256=026e2686c837a09db5cb3fb54b0f022e5d8e16c375734b9cefee17f5f96b61b7 -->
# phil/qr-code-bundle

**Des codes QR dans une application Symfony : dans Twig, dans un composant accessible, à une adresse mise en cache,
dans les documents UJO et dans les documents PDF.**

phil/qr-code-bundle apporte [phil/qr-code](../qr-code/README.md) dans Symfony. La configuration fixe les valeurs par
défaut de chaque code — niveau de correction d'erreurs, zone de silence, couleurs, taille, en-tête ECI, kanji, la plus
longue charge utile — et les logos qu'un gabarit peut nommer. `qr_code()` dessine un texte ou un contenu typé dans Twig
en SVG en ligne ou en URI `data:` ; le composant `<twig:PhilQrCode:QrCode>` place le code dans une figure, avec ce
qu'il contient écrit à côté ; une route signée sert les codes en SVG ou en PNG avec un long cache HTTP. Les blocs
`qrCode` des documents UJO (Universal JSON Output) sont dessinés avec les mêmes valeurs par défaut pour
phil/svg-blocks-bundle, et `PdfQrCodes` dessine des codes dans les documents PDF de phil/paged-media, en vectoriel.

Il s'adresse à toute application Symfony qui affiche ou imprime des codes QR : billets, cartes-cadeaux, affiches,
cartes de contact, affichettes Wi-Fi, pages et livres. Chaque code est dessiné sur le serveur ; rien n'est envoyé à
un service.

## État

| Partie | État |
|---|---|
| Configuration des valeurs par défaut et des logos, vérifiée à la construction du conteneur | Implémenté et testé |
| Fonction et filtre `qr_code()` : SVG en ligne, URI `data:` SVG ou PNG ; `qr_code_label()` | Implémenté et testé |
| Contenus typés à partir de tableaux : URL, courriel, téléphone, SMS, lieu, Wi-Fi, MeCard, vCard, événement | Implémenté et testé |
| `<twig:PhilQrCode:QrCode>` : une figure, une légende qui dit ce que contient le code, un lien pour une adresse | Implémenté et testé |
| La route signée `/qr-code.svg` et `/qr-code.png`, publique et immuable, `ETag` et `304` ; `qr_code_url()` | Implémenté et testé |
| Les blocs `qrCode` des documents UJO dessinés pour phil/svg-blocks-bundle | Implémenté et testé |
| `PdfQrCodes` : des codes dessinés dans les documents PDF de phil/paged-media, logo compris | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, activation du bundle, import de la route. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_qr_code`, et les logos. |
| [Twig](doc/fr/twig.md) | `qr_code()`, ses options et ses sorties, `qr_code_label()`, les contenus typés en tableaux. |
| [Le composant](doc/fr/component.md) | `<twig:PhilQrCode:QrCode>`, ses props et son accessibilité. |
| [La route](doc/fr/route.md) | `qr_code_url()`, les adresses signées et leur cache. |
| [Blocs UJO et documents PDF](doc/fr/blocks-and-pdf.md) | Les blocs `qrCode` et `PdfQrCodes`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que l'application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/qr-code-bundle
```

```yaml
# config/packages/phil_qr_code.yaml
phil_qr_code:
    error_correction: Q
    logos:
        brand: { path: '%kernel.project_dir%/assets/logo.svg', zone: 0.2 }
```

```twig
{{ qr_code('https://example.com/tickets/42', {title: 'Ticket 42'}) }}
<twig:PhilQrCode:QrCode :content="{type: 'wifi', ssid: 'Gnome Café', password: 'tea-and-dice'}" logo="brand" />
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-sodium`, Symfony 8.2 ou plus récent, Twig 3.21 ou plus récent, et `phil/qr-code`.
Trois intégrations sont facultatives, suggérées plutôt qu'exigées, et enregistrées seulement quand leur paquet est
installé : Symfony UX Twig Component pour le composant, phil/svg-blocks (ou phil/svg-blocks-bundle) pour les blocs
`qrCode` des documents UJO, et phil/paged-media pour les codes QR dans les documents PDF. Le bundle s'installe et
passe ses tests sans elles.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
