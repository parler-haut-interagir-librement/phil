<!-- phil-dev source-sha256=99a92f2433bff9f7e926be979cafc097b2388177f03fe2abd948a8796a4adb34 -->
# PHIL

**Parler Haut, Interagir Librement : des bibliothèques PHP et des bundles Symfony ouverts pour les sites qui publient,
font participer et protègent leurs visiteurs.**

PHIL est un ensemble de petits paquets `phil/*` qui font chacun une seule chose : une bibliothèque en PHP pur, le plus
souvent accompagnée d'un bundle Symfony qui la branche aux formulaires, à la validation, à Twig, à Doctrine et à la
configuration. Ils sont typés pour PHPStan à son niveau maximal et testés jusqu'à la mutation de chaque
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
| [`phil/references-bundle`](https://github.com/parler-haut-interagir-librement/references-bundle) | Sachez où chaque ressource est utilisée, et refusez d'en supprimer une dont quelque chose a encore besoin. |
| [`phil/trash-bundle`](https://github.com/parler-haut-interagir-librement/trash-bundle) | Une corbeille pour les pages et les autres ressources supprimées : restaurez-les, ou purgez-les après un délai fixé. |

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
| [`phil/markup`](https://github.com/parler-haut-interagir-librement/markup) | Du Markdown, du BBCode, du MediaWiki, du DokuWiki, du Creole, de l'UJO, du HTML ou du texte brut, changés en HTML qu'on peut afficher sans risque. |
| [`phil/markup-bundle`](https://github.com/parler-haut-interagir-librement/markup-bundle) | Le Markdown, le BBCode, les wikis, UJO et le HTML des utilisateurs rendus de façon sûre dans Twig, convertis en UJO, avec phil/markup sous forme de services Symfony. |
| [`phil/svg-sanitizer`](https://github.com/parler-haut-interagir-librement/svg-sanitizer) | Laissez les gens téléverser et coller des images SVG sans leur permettre d'exécuter du code, de charger ce qu'ils ne devraient pas ou d'intégrer un autre document. |
| [`phil/qr-code-bundle`](https://github.com/parler-haut-interagir-librement/qr-code-bundle) | Des codes QR dans une application Symfony : dans Twig, dans un composant accessible, à une adresse mise en cache, dans les documents UJO et dans les documents PDF. |
| [`phil/block-editor-bundle`](https://github.com/parler-haut-interagir-librement/block-editor-bundle) | Un éditeur par blocs pour les formulaires Symfony, en contrôleurs Stimulus, qui écrit des documents UJO (Universal JSON Output) vérifiés sur le serveur. |
| [`phil/edit-lock-bundle`](https://github.com/parler-haut-interagir-librement/edit-lock-bundle) | Un rédacteur par bloc : des baux d'édition, la présence et les enregistrements de blocs poussés par Mercure, pour les applications Symfony. |
| [`phil/footnotes-sidenotes`](https://github.com/parler-haut-interagir-librement/footnotes-sidenotes) | Des notes de bas de page et des notes en marge pour les documents UJO (Universal JSON Output), à l'écran et à l'impression. |
| [`phil/text-diff-bundle`](https://github.com/parler-haut-interagir-librement/text-diff-bundle) | Ce qui a changé entre deux versions, affiché dans un gabarit et parcouru changement par changement. |
| [`phil/svg-chart`](https://github.com/parler-haut-interagir-librement/svg-chart) | Des graphiques dessinés en SVG sur le serveur, à partir de la configuration qu'écrit déjà un éditeur de graphiques. |
| [`phil/content-warnings`](https://github.com/parler-haut-interagir-librement/content-warnings) | Des avertissements de contenu pour les jeux, les livres, les articles et les événements, et les thèmes que chaque lecteur replie ou masque. |
| [`phil/content-warnings-bundle`](https://github.com/parler-haut-interagir-librement/content-warnings-bundle) | Des avertissements de contenu dans les applications Symfony : un catalogue tiré de la configuration, des avertissements sur n'importe quelle entité, et du contenu replié ou masqué selon le choix de chaque lecteur. |
| [`phil/taxonomy-bundle`](https://github.com/parler-haut-interagir-librement/taxonomy-bundle) | Des étiquettes et un arbre de catégories traduit pour toute sorte de ressource, avec des types de formulaire pour les modifier. |

### Formulaires et protection

| Paquet | Description |
|---|---|
| [`phil/spam-protection`](https://github.com/parler-haut-interagir-librement/spam-protection) | Repérez le pourriel dans l'envoi d'un formulaire sans captcha, en PHP simple. |
| [`phil/spam-protection-bundle`](https://github.com/parler-haut-interagir-librement/spam-protection-bundle) | Tenez le pourriel hors de vos formulaires Symfony sans captcha, sans tiers et sans identifiant. |
| [`phil/bot-detection-bundle`](https://github.com/parler-haut-interagir-librement/bot-detection-bundle) | Distinguez un robot d'un humain dans le navigateur, et ne laissez sortir que ce seul mot. |
| [`phil/contact-form-bundle`](https://github.com/parler-haut-interagir-librement/contact-form-bundle) | Une page « écrivez-nous » pour Symfony, protégée du pourriel sans captcha. |
| [`phil/passwords`](https://github.com/parler-haut-interagir-librement/passwords) | Dites aux gens à quel point leur mot de passe se devine, refusez ceux qui ont déjà fui et donnez-leur-en de bons. |
| [`phil/upload-scan`](https://github.com/parler-haut-interagir-librement/upload-scan) | Chaque fichier téléversé passe par un antivirus avant que quiconque puisse l'ouvrir. |

### Argent, téléphones et dates

| Paquet | Description |
|---|---|
| [`phil/money`](https://github.com/parler-haut-interagir-librement/money) | Des montants d'argent sous forme d'entiers exacts dans une devise connue, sans aucun nombre à virgule flottante. |
| [`phil/money-bundle`](https://github.com/parler-haut-interagir-librement/money-bundle) | Des montants d'argent dans les formulaires, la validation, les gabarits Twig et les entités Doctrine de Symfony, sans nombres à virgule flottante. |
| [`phil/phone`](https://github.com/parler-haut-interagir-librement/phone) | Des numéros de téléphone lus comme les gens les saisissent, vérifiés selon le plan de leur pays, puis formatés. |
| [`phil/phone-bundle`](https://github.com/parler-haut-interagir-librement/phone-bundle) | Un champ de numéro de téléphone, une contrainte de validation, un type de colonne et des filtres Twig pour Symfony. |
| [`phil/recurrence`](https://github.com/parler-haut-interagir-librement/recurrence) | Des dates qui se répètent selon une règle RFC 5545 : lisez-la, vérifiez-la, écrivez-la, listez ses occurrences. |
| [`phil/recurrence-bundle`](https://github.com/parler-haut-interagir-librement/recurrence-bundle) | Un formulaire pour les événements qui se répètent, une colonne pour enregistrer la règle, et la règle en mots. |
| [`phil/tax`](https://github.com/parler-haut-interagir-librement/tax) | Plusieurs taxes sur une même vente, chacune calculée sur la même base et détaillée au cent près. |
| [`phil/opening-hours`](https://github.com/parler-haut-interagir-librement/opening-hours) | Les heures d'ouverture d'un lieu, et s'il est ouvert en ce moment. |
| [`phil/opening-hours-bundle`](https://github.com/parler-haut-interagir-librement/opening-hours-bundle) | Un formulaire d'heures d'ouverture, une colonne JSON et des heures localisées dans Twig, pour Symfony. |

### Liens, adresses et lieux

| Paquet | Description |
|---|---|
| [`phil/url`](https://github.com/parler-haut-interagir-librement/url) | Des adresses Web comme des valeurs normalisées, avec leur genre détecté : Facebook, YouTube, Mastodon, un site Web. |
| [`phil/url-bundle`](https://github.com/parler-haut-interagir-librement/url-bundle) | Des champs de lien qui détectent leur genre, une contrainte de validation, un type de colonne et des filtres Twig. |
| [`phil/instant-messenger`](https://github.com/parler-haut-interagir-librement/instant-messenger) | Des identifiants de messagerie lus, affichés et liés selon chaque service : WhatsApp, Telegram, Signal, Matrix et d'autres. |
| [`phil/instant-messenger-bundle`](https://github.com/parler-haut-interagir-librement/instant-messenger-bundle) | Un champ d'identifiant de messagerie avec son service, une contrainte de validation et des filtres Twig pour Symfony. |
| [`phil/address`](https://github.com/parler-haut-interagir-librement/address) | Des adresses postales vérifiées et écrites comme chaque pays les écrit. |
| [`phil/address-bundle`](https://github.com/parler-haut-interagir-librement/address-bundle) | Un formulaire d'adresse pour Symfony dont les champs suivent le pays choisi. |
| [`phil/geo`](https://github.com/parler-haut-interagir-librement/geo) | Des coordonnées, des distances et des zones englobantes sous forme de valeurs PHP, et le géocodage par le fournisseur de votre choix. |
| [`phil/geo-bundle`](https://github.com/parler-haut-interagir-librement/geo-bundle) | Des coordonnées stockées, des distances dans Twig, des géocodeurs configurés et des listes triées du plus proche au plus loin pour les applications Symfony. |

### Site et navigation

| Paquet | Description |
|---|---|
| [`phil/menu-bundle`](https://github.com/parler-haut-interagir-librement/menu-bundle) | Des menus de navigation et des fils d'Ariane pour Symfony, avec la page courante du visiteur marquée. |
| [`phil/redirects-bundle`](https://github.com/parler-haut-interagir-librement/redirects-bundle) | Des redirections pour Symfony, gérées depuis une administration et résolues seulement quand rien d'autre n'a répondu. |
| [`phil/sitemap`](https://github.com/parler-haut-interagir-librement/sitemap) | Le plan de site XML des moteurs de recherche, construit à partir de pages vérifiées et coupé aux limites du protocole. |
| [`phil/sitemap-bundle`](https://github.com/parler-haut-interagir-librement/sitemap-bundle) | `/sitemap.xml` pour un site Symfony, à partir d'options de route, de fournisseurs d'URL et d'un événement. |
| [`phil/structured-data`](https://github.com/parler-haut-interagir-librement/structured-data) | Le graphe schema.org d'une page sous forme de valeurs : des nœuds venus de toutes les parties d'un site, fusionnés par `@id`, vérifiés, puis imprimés dans un seul élément de script JSON-LD. |
| [`phil/well-known-bundle`](https://github.com/parler-haut-interagir-librement/well-known-bundle) | Les adresses `/.well-known/` d'un site Symfony, chacune activée dans sa configuration. |
| [`phil/http-cache-tags-bundle`](https://github.com/parler-haut-interagir-librement/http-cache-tags-bundle) | Mettez des pages entières en cache dans Varnish ou un CDN, et purgez exactement celles qu'une modification change. |

### Communauté et événements

| Paquet | Description |
|---|---|
| [`phil/poll`](https://github.com/parler-haut-interagir-librement/poll) | Un moteur de vote : des bulletins vérifiés selon les règles de leur scrutin, puis dépouillés en classement. |
| [`phil/reservation`](https://github.com/parler-haut-interagir-librement/reservation) | Le cœur des réservations : une ressource, ses créneaux, ses règles, ses conflits et ses rappels. |
| [`phil/checkin`](https://github.com/parler-haut-interagir-librement/checkin) | L'enregistrement à l'entrée d'un événement ou d'un lieu : un code lu à une porte, décidé aussitôt, enregistré une fois, en ligne ou hors ligne. |
| [`phil/listings`](https://github.com/parler-haut-interagir-librement/listings) | Le noyau des annonces : une petite annonce, une offre d'emploi, une recherche de partenaires, rédigées, modérées, reçoivent des réponses et expirent selon les mêmes règles. |
| [`phil/open-badges`](https://github.com/parler-haut-interagir-librement/open-badges) | Des Open Badges 3.0 que tout le monde peut vérifier : émis, signés, intégrés à l'image, révoqués, appuyés et réunis dans un relevé d'apprentissage. |

### Impression et produits

| Paquet | Description |
|---|---|
| [`phil/fonts`](https://github.com/parler-haut-interagir-librement/fonts) | Ce qu'un fichier de police dit de lui-même (ses noms, sa graisse, sa licence d'incorporation) et la règle `@font-face` pour le servir. |
| [`phil/print-preflight`](https://github.com/parler-haut-interagir-librement/print-preflight) | Ce qu'un imprimeur refuserait, avant que le fichier lui parvienne. |
| [`phil/print-compositor`](https://github.com/parler-haut-interagir-librement/print-compositor) | Les maquettes que montre une boutique et les fichiers d'impression que reçoit un imprimeur, dessinés à partir des mêmes calques. |
| [`phil/commerce-product-template`](https://github.com/parler-haut-interagir-librement/commerce-product-template) | Un gabarit et un visuel donnent chaque produit d'un catalogue : chaque UGS, son prix, ses canaux et ses illustrations. |

### Intégrations

| Paquet | Description |
|---|---|
| [`phil/standard-webhooks`](https://github.com/parler-haut-interagir-librement/standard-webhooks) | Signez et vérifiez des webhooks que n'importe quelle bibliothèque Standard Webhooks peut envoyer ou vérifier. |
| [`phil/standard-webhooks-bundle`](https://github.com/parler-haut-interagir-librement/standard-webhooks-bundle) | Des livraisons de webhooks vérifiées en arguments de contrôleur, et des webhooks signés envoyés depuis Symfony. |
| [`phil/circuit-breaker`](https://github.com/parler-haut-interagir-librement/circuit-breaker) | Un disjoncteur par tiers : les appels cessent aussitôt quand il échoue sans arrêt, et reprennent quand il se rétablit. |
| [`phil/circuit-breaker-bundle`](https://github.com/parler-haut-interagir-librement/circuit-breaker-bundle) | Des circuits déclarés en configuration, leurs états partagés par les workers, et des clients HTTP derrière leurs circuits. |
| [`phil/bounce-parser`](https://github.com/parler-haut-interagir-librement/bounce-parser) | Sachez ce que dit chaque message de votre boîte de rebonds, et quoi faire de l'adresse qu'il nomme. |

### Exploitation

| Paquet | Description |
|---|---|
| [`phil/feature-flags-bundle`](https://github.com/parler-haut-interagir-librement/feature-flags-bundle) | Désactivez une fonctionnalité sans déploiement, menez un test A/B et forcez l'ouverture du circuit d'un tiers défaillant. |
| [`phil/console-bundle`](https://github.com/parler-haut-interagir-librement/console-bundle) | Des commandes de console Symfony plus sûres. |
| [`phil/migration`](https://github.com/parler-haut-interagir-librement/migration) | Des notes de version et des nouvelles que les migrations Doctrine envoient au clavardage une fois l'exécution terminée. |
| [`phil/migration-bundle`](https://github.com/parler-haut-interagir-librement/migration-bundle) | Chaque exécution de `doctrine:migrations:migrate` annoncée une seule fois sur vos canaux de clavardage. |
| [`phil/backup`](https://github.com/parler-haut-interagir-librement/backup) | Sauvegardez une base de données et ses fichiers médias, chiffrés et en rotation, restaurez-les derrière une garde, et prouvez qu'ils se restaurent. |
| [`phil/backup-bundle`](https://github.com/parler-haut-interagir-librement/backup-bundle) | Sauvegardez la base de données et les fichiers médias d'une application Symfony à partir de sa configuration, restaurez-les derrière une garde, et testez qu'ils se restaurent. |
| [`phil/monitoring-bundle`](https://github.com/parler-haut-interagir-librement/monitoring-bundle) | La surveillance d'un site Symfony : sa santé, un mode maintenance, les battements de cœur de ses tâches planifiées et ses files d'attente Messenger. |
| [`phil/reporting-bundle`](https://github.com/parler-haut-interagir-librement/reporting-bundle) | Des tableaux de bord et des rapports pour les applications Symfony : des chiffres fournis par n'importe quel bundle, des graphiques dessinés sans script, des rapports exportés, planifiés et livrés. |
| [`phil/route-access-check`](https://github.com/parler-haut-interagir-librement/route-access-check) | Chaque route a une règle d'accès, ou dit pourquoi elle est publique : c'est vérifié en intégration continue, avant la mise en production. |
| [`phil/route-access-check-bundle`](https://github.com/parler-haut-interagir-librement/route-access-check-bundle) | `bin/console phil:route-access:check` : chaque route de l'application a une règle d'accès, sinon l'étape d'intégration continue échoue. |

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
