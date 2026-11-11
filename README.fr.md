<!-- phil-dev source-sha256=99a92f2433bff9f7e926be979cafc097b2388177f03fe2abd948a8796a4adb34 -->
# PHIL

**Parler Haut, Interagir Librement : des bibliothèques PHP et des bundles Symfony ouverts pour les sites qui publient,
font participer et protègent leurs visiteurs.**

PHIL est un ensemble de petits paquets `phil/*` qui font chacun une seule chose : une bibliothèque en PHP pur, le plus
souvent accompagnée d'un bundle Symfony qui la branche aux formulaires, à la validation, à Twig, à Doctrine et à la
configuration. Ils sont écrits de zéro, typés pour PHPStan à son niveau maximal et testés jusqu'à la mutation de chaque
ligne de leur domaine.

Ce dépôt est le **monodépôt** où tous les paquets sont développés, testés et documentés ensemble. Chaque paquet est
ensuite copié dans son propre dépôt, en **lecture seule**, d'où Composer l'installe par Packagist.

## Paquets

### Énumérations

| Paquet | Description |
|---|---|
| [`phil/enums`](https://github.com/parler-haut-interagir-librement/enums) | Des traits et des attributs qui rendent les enums PHP plus faciles à énumérer, à comparer, à décrire et à regrouper en ensembles. |
| [`phil/enums-bundle`](https://github.com/parler-haut-interagir-librement/enums-bundle) | Vos enums PHP dans les formulaires, les traductions, Twig, la validation, Doctrine et les contrats d'API de Symfony. |

### Architecture

| Paquet | Description |
|---|---|
| [`phil/resource`](https://github.com/parler-haut-interagir-librement/resource) | Désignez n'importe quel enregistrement de votre application par son genre et son identifiant, comme `page:42`. |
| [`phil/ddd-bundle`](https://github.com/parler-haut-interagir-librement/ddd-bundle) | Les quelques types que partagent tous les contextes délimités : identifiants, événements du domaine, erreurs, bus de commandes et de requêtes. |

### Texte et documents

| Paquet | Description |
|---|---|
| [`phil/slug`](https://github.com/parler-haut-interagir-librement/slug) | Des slugs d'URL faits à partir de titres et de noms, sous forme d'un type qui n'accepte rien d'autre. |
| [`phil/slug-bundle`](https://github.com/parler-haut-interagir-librement/slug-bundle) | Des générateurs de slugs nommés, déclarés dans la configuration, autoconnectés par leur nom et utilisables dans Twig. |
| [`phil/ujo`](https://github.com/parler-haut-interagir-librement/ujo) | Des documents UJO (Universal JSON Output) comme des valeurs fiables : lus strictement, vérifiés bloc par bloc, rendus en HTML sûr. |
| [`phil/ujo-bundle`](https://github.com/parler-haut-interagir-librement/ujo-bundle) | Des documents UJO (Universal JSON Output) dans une application Symfony : un champ qui les vérifie, une contrainte, et du HTML sûr dans Twig. |
| [`phil/typography`](https://github.com/parler-haut-interagir-librement/typography) | De vrais guillemets, des tirets, les espaces du français et des traits d'union conditionnels dans votre HTML, dans toutes les langues, sans toucher au balisage. |
| [`phil/typography-bundle`](https://github.com/parler-haut-interagir-librement/typography-bundle) | Des guillemets typographiques, des tirets, des espaces françaises et des césures conditionnelles partout où une application Symfony affiche du texte. |
| [`phil/text-diff`](https://github.com/parler-haut-interagir-librement/text-diff) | Ce qui a changé entre deux textes, mot par mot, paragraphe par paragraphe ou bloc par bloc — affiché en HTML ou énuméré. |
| [`phil/highlight`](https://github.com/parler-haut-interagir-librement/highlight) | Du code en 24 langages coloré sur le serveur, en simples balises span HTML que n'importe quelle feuille de style peut habiller. |
| [`phil/highlight-bundle`](https://github.com/parler-haut-interagir-librement/highlight-bundle) | Du code coloré sur le serveur dans une application Symfony : dans les documents UJO, dans les gabarits, avec un thème prêt à lier. |
| [`phil/css-sanitizer`](https://github.com/parler-haut-interagir-librement/css-sanitizer) | Laissez les gens styliser leurs pages sans que leur CSS exécute du code, charge ce qu'il ne doit pas ou sorte de son cadre. |
| [`phil/qr-code`](https://github.com/parler-haut-interagir-librement/qr-code) | Des codes QR dessinés en SVG ou en PNG par PHP seul, sans service et sans bibliothèque d'images. |

### Formulaires et protection

| Paquet | Description |
|---|---|
| [`phil/spam-protection`](https://github.com/parler-haut-interagir-librement/spam-protection) | Repérez le pourriel dans l'envoi d'un formulaire sans captcha, en PHP simple. |
| [`phil/spam-protection-bundle`](https://github.com/parler-haut-interagir-librement/spam-protection-bundle) | Tenez le pourriel hors de vos formulaires Symfony sans captcha, sans tiers et sans identifiant. |
| [`phil/bot-detection-bundle`](https://github.com/parler-haut-interagir-librement/bot-detection-bundle) | Distinguez un robot d'un humain dans le navigateur, et ne laissez sortir que ce seul mot. |

### Argent, téléphones et dates

| Paquet | Description |
|---|---|
| [`phil/money`](https://github.com/parler-haut-interagir-librement/money) | Des montants d'argent sous forme d'entiers exacts dans une devise connue, sans aucun nombre à virgule flottante. |
| [`phil/money-bundle`](https://github.com/parler-haut-interagir-librement/money-bundle) | Des montants d'argent dans les formulaires, la validation, les gabarits Twig et les entités Doctrine de Symfony, sans nombres à virgule flottante. |
| [`phil/phone`](https://github.com/parler-haut-interagir-librement/phone) | Des numéros de téléphone lus comme les gens les saisissent, vérifiés selon le plan de leur pays, puis formatés. |
| [`phil/phone-bundle`](https://github.com/parler-haut-interagir-librement/phone-bundle) | Un champ de numéro de téléphone, une contrainte de validation, un type de colonne et des filtres Twig pour Symfony. |
| [`phil/recurrence`](https://github.com/parler-haut-interagir-librement/recurrence) | Des dates qui se répètent selon une règle RFC 5545 : lisez-la, vérifiez-la, écrivez-la, listez ses occurrences. |
| [`phil/recurrence-bundle`](https://github.com/parler-haut-interagir-librement/recurrence-bundle) | Un formulaire pour les événements qui se répètent, une colonne pour enregistrer la règle, et la règle en mots. |

### Intégrations

| Paquet | Description |
|---|---|
| [`phil/standard-webhooks`](https://github.com/parler-haut-interagir-librement/standard-webhooks) | Signez et vérifiez des webhooks que n'importe quelle bibliothèque Standard Webhooks peut envoyer ou vérifier. |
| [`phil/standard-webhooks-bundle`](https://github.com/parler-haut-interagir-librement/standard-webhooks-bundle) | Des livraisons de webhooks vérifiées en arguments de contrôleur, et des webhooks signés envoyés depuis Symfony. |
| [`phil/circuit-breaker`](https://github.com/parler-haut-interagir-librement/circuit-breaker) | Un disjoncteur par tiers : les appels cessent aussitôt quand il échoue sans arrêt, et reprennent quand il se rétablit. |
| [`phil/circuit-breaker-bundle`](https://github.com/parler-haut-interagir-librement/circuit-breaker-bundle) | Des circuits déclarés en configuration, leurs états partagés par les workers, et des clients HTTP derrière leurs circuits. |

Tous les paquets sont en `0.x` : leur API peut encore changer d'une version mineure à l'autre, comme le consigne leur
CHANGELOG. D'autres paquets rejoignent la vitrine par petits groupes quotidiens.

## Installer un paquet

Demandez le paquet voulu ; un bundle amène sa bibliothèque avec lui :

```sh
composer require phil/money-bundle
```

Le README et le dossier `doc/` de chaque paquet disent ce qu'il exige, comment le configurer et ce qu'il laisse à
l'application.

## Organisation

- `packages/<nom>/` : un paquet par dossier, installable seul, avec son `composer.json`, son README en anglais et en
  français, son CHANGELOG, sa licence, sa documentation dans `doc/` (miroir français dans `doc/fr/`) et ses tests.
- La racine les relie par un dépôt Composer `path` et porte l'outillage de qualité commun, qui vient de
  [`phil/dev-tools`](https://github.com/parler-haut-interagir-librement/dev-tools).

## Contribuer

Les tickets et les demandes de fusion vont dans **ce dépôt**, jamais dans les dépôts copiés, que chaque copie écrase.
Lisez le [guide de contribution](CONTRIBUTING.md) et le [code de conduite](CODE_OF_CONDUCT.md). Signalez une
vulnérabilité en privé, comme l'explique la [politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE). Chaque paquet porte son propre fichier de licence.
