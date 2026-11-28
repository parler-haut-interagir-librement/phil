<!-- phil-dev source-sha256=9d947c31f8f05ce1e936b9fb63edafd46cd9c1f57a22de22504ffbd3cbf6c971 -->
# PHIL

**Parler Haut, Interagir Librement : des bibliothèques PHP et des bundles Symfony ouverts pour les sites qui publient,
font participer et protègent leurs visiteurs.**

PHIL est un ensemble de petits paquets `phil/*` qui font chacun une seule chose : une bibliothèque en PHP pur, le plus
souvent accompagnée d'un bundle Symfony qui la branche aux formulaires, à la validation, à Twig, à Doctrine et à la
configuration. Ils sont typés pour PHPStan à son niveau maximal et testés jusqu'à la mutation de chaque
ligne de leur domaine.

Ce dépôt est le **monodépôt** où tous les paquets sont développés, testés et documentés ensemble. Chaque paquet est
ensuite copié dans son propre dépôt, en **lecture seule**, d'où Composer l'installe par Packagist.

## Principes

Douze principes valent pour chaque paquet ; le [guide de contribution](CONTRIBUTING.fr.md#principes) traduit chacun en
règles que suit un changement.

1. **Sûr par défaut.** Les réglages prudents sont ceux qu'on obtient sans rien faire, et toute entrée est hostile tant
   qu'elle n'a pas été vérifiée.
2. **Ouvert, en entier.** Chaque paquet est sous MIT, développé à découvert, sans accord de contributeur à signer.
3. **Natif à Symfony.** Si vous connaissez Symfony, vous savez où regarder : Composer, une recette Flex, un bundle
   standard, vos fichiers de configuration habituels.
4. **Bilingue, toutes les langues.** Documenté en anglais et en français, tenus ensemble ; bâti pour toutes les
   langues, pas seulement les nôtres.
5. **La vie privée dès la conception.** Pensé pour la Loi 25 et le RGPD : rien ne part chez un tiers sans que le site
   le décide.
6. **Rapide, mesuré, sûr en mode worker.** La vitesse est mesurée par des bancs sur les chemins chauds, pas supposée,
   et chaque paquet est sûr dans un worker qui dure.
7. **Accessible d'office.** Le HTML sémantique d'abord, utilisable au clavier et au lecteur d'écran, et jamais une
   information portée par la seule couleur.
8. **Un vrai besoin.** Un paquet existe parce qu'un vrai site en a besoin ; une idée sans usager reste en incubation.
9. **Peu à configurer.** Il marche sans configuration ; les options n'existent que pour de vrais choix.
10. **Utile seul, meilleur ensemble.** Chaque paquet sert seul ; ensemble, ils partagent noms, configuration et points
    d'extension.
11. **Prévisible et extensible.** Des points d'extension documentés laissent les add-ons se brancher sans correctif, et
    rien n'arrive par magie cachée.
12. **Une qualité prouvée.** PHPStan au maximum, chaque mutant tué, chaque requis à sa dernière mineure publiée, le PHP
    et le Symfony suivants testés dès leur première version candidate.

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
| [`phil/tenancy-bundle`](https://github.com/parler-haut-interagir-librement/tenancy-bundle) | Le locataire actif d'une application multilocataire : résolu par le compte ou l'hôte, gardé par un filtre Doctrine fermé par défaut, porté par Messenger, les sessions, le courriel et les caches. |
| [`phil/tenancy-database-bundle`](https://github.com/parler-haut-interagir-librement/tenancy-database-bundle) | Une base par locataire, ou la base commune : la connexion suit le locataire, les migrations passent base par base, et un locataire change de base, vérifié et réversible. |
| [`phil/settings-bundle`](https://github.com/parler-haut-interagir-librement/settings-bundle) | Des réglages que chacun change lui-même : déclarés par les paquets, lus typés avec leur défaut, un centre de préférences, des réglages du site et des préférences d'affichage appliquées à la page. |
| [`phil/outbox`](https://github.com/parler-haut-interagir-librement/outbox) | Une boîte d'envoi transactionnelle : des événements enregistrés avec l'écriture qu'ils décrivent, relayés au moins une fois, dans l'ordre de leur clé, avec nouveaux essais et enregistrements morts. |
| [`phil/outbox-bundle`](https://github.com/parler-haut-interagir-librement/outbox-bundle) | La boîte d'envoi dans Symfony : une table de la connexion Doctrine DBAL, une commande de relais sûre en mode worker, un déclencheur planifié et un pont facultatif vers Messenger. |
| [`phil/rules`](https://github.com/parler-haut-interagir-librement/rules) | Décidez des prix, des promotions, des accès ou des publics par des règles enregistrées : conditions all/any/not typées, coût borné, trace du pourquoi, JSON versionné, aucun eval. |
| [`phil/bulk-actions`](https://github.com/parler-haut-interagir-librement/bulk-actions) | Modifiez des milliers d'enregistrements sans risque depuis une liste d'administration : sélection côté serveur, changements par champ, aperçu, voters et versions par élément, passes par lots, nouvelles tentatives. |

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
| [`phil/timed-text`](https://github.com/parler-haut-interagir-librement/timed-text) | Sous-titres, chapitres et transcriptions lus et écrits en WebVTT, en SRT et en Podcasting 2.0, en millisecondes entières, décalés, fusionnés et coupés. |
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
| [`phil/epub`](https://github.com/parler-haut-interagir-librement/epub) | Des livres EPUB 3 à partir de HTML purifié ou de documents UJO : navigation, métadonnées d'accessibilité, polices, superpositions média et mise en page fixe. |
| [`phil/document-import`](https://github.com/parler-haut-interagir-librement/document-import) | DOCX, ODT, RTF, texte de PDF, Markdown et HTML changés en documents UJO (Universal JSON Output), avec un rapport de ce qui a été laissé de côté ; UJO exporté en DOCX et en ODT. |
| [`phil/document-import-bundle`](https://github.com/parler-haut-interagir-librement/document-import-bundle) | Des fichiers Word, OpenDocument, RTF et PDF importés en documents UJO dans Symfony : analysés d'abord, limites et correspondance des styles configurées, images gardées dans la médiathèque. |
| [`phil/braille-export`](https://github.com/parler-haut-interagir-librement/braille-export) | Des documents exportés en braille : braille intégral français et anglais en PHP, braille abrégé par un traducteur, mise en page braille, et BRF, PEF et Unicode. |
| [`phil/braille-export-liblouis`](https://github.com/parler-haut-interagir-librement/braille-export-liblouis) | Le braille abrégé et intégral dans toutes les langues dont liblouis a les tables, par son programme lou_translate, comme traducteur de phil/braille-export. |
| [`phil/epub-bundle`](https://github.com/parler-haut-interagir-librement/epub-bundle) | Des livres bâtis par des sources déclarées par attribut, écrits tout de suite ou par un message Messenger, avec leurs polices et leurs images. |
| [`phil/latex-mathml`](https://github.com/parler-haut-interagir-librement/latex-mathml) | Des formules LaTeX converties en MathML Core, toute autre commande refusée avec sa position, longueur, profondeur et expansion bornées. |
| [`phil/svg-blocks`](https://github.com/parler-haut-interagir-librement/svg-blocks) | Les blocs math, mermaid, chart et code QR des documents UJO dessinés une fois à l'enregistrement, en SVG ou MathML signés. |
| [`phil/number-words`](https://github.com/parler-haut-interagir-librement/number-words) | Nombres en toutes lettres sur ICU : cardinaux, ordinaux, genre et montants d'argent, corrigés pour six langues. |
| [`phil/citations`](https://github.com/parler-haut-interagir-librement/citations) | Références gardées en CSL-JSON, import BibTeX et RIS, et styles auteur-date, notes et numérique en anglais et en français. |
| [`phil/slides`](https://github.com/parler-haut-interagir-librement/slides) | Des diapositives dans Symfony à partir d'un document UJO ou de Markdown : notes de l'orateur cachées au public, fragments, vue du présentateur derrière une route protégée facultative, et export PDF. |
| [`phil/profile-editorial`](https://github.com/parler-haut-interagir-librement/profile-editorial) | Un seul require pour les paquets qui publient du contenu éditorial : balisage sûr, citations, notes de bas de page et notes en marge, avertissements de contenu et données schema.org. |
| [`phil/spreadsheet`](https://github.com/parler-haut-interagir-librement/spreadsheet) | Lisez et écrivez du CSV, du XLSX et de l'ODS en mémoire constante, avec des types de cellules explicites, une garde contre l'injection de formules et des fichiers non fiables lus dans des limites. |
| [`phil/email-templates-bundle`](https://github.com/parler-haut-interagir-librement/email-templates-bundle) | Laissez les sites modifier leurs courriels sans risque : gabarits par site et par locale, avec versions, Twig en bac à sable, composants adaptés au courriel et aperçu. |
| [`phil/email-preview`](https://github.com/parler-haut-interagir-librement/email-preview) | Repérez ce qui brise un courriel avant son envoi : partie texte absente, aucun désabonnement en un clic, pixels de suivi, styles oubliés, trop large ou trop long. |
| [`phil/svg-blocks-bundle`](https://github.com/parler-haut-interagir-librement/svg-blocks-bundle) | Des blocs de mathématiques, Mermaid, graphiques et codes QR dans Symfony : dessinés une fois et signés, rendus dans les documents, avec un aperçu en direct et des diagrammes dessinés en arrière-plan. |

### Formulaires et protection

| Paquet | Description |
|---|---|
| [`phil/spam-protection`](https://github.com/parler-haut-interagir-librement/spam-protection) | Repérez le pourriel dans l'envoi d'un formulaire sans captcha, en PHP simple. |
| [`phil/spam-protection-bundle`](https://github.com/parler-haut-interagir-librement/spam-protection-bundle) | Tenez le pourriel hors de vos formulaires Symfony sans captcha, sans tiers et sans identifiant. |
| [`phil/bot-detection-bundle`](https://github.com/parler-haut-interagir-librement/bot-detection-bundle) | Distinguez un robot d'un humain dans le navigateur, et ne laissez sortir que ce seul mot. |
| [`phil/contact-form-bundle`](https://github.com/parler-haut-interagir-librement/contact-form-bundle) | Une page « écrivez-nous » pour Symfony, protégée du pourriel sans captcha. |
| [`phil/passwords`](https://github.com/parler-haut-interagir-librement/passwords) | Dites aux gens à quel point leur mot de passe se devine, refusez ceux qui ont déjà fui et donnez-leur-en de bons. |
| [`phil/upload-scan`](https://github.com/parler-haut-interagir-librement/upload-scan) | Chaque fichier téléversé passe par un antivirus avant que quiconque puisse l'ouvrir. |
| [`phil/forms-autosave`](https://github.com/parler-haut-interagir-librement/forms-autosave) | La saisie d'un formulaire gardée pendant qu'on le remplit, rendue après un rechargement, oubliée une fois envoyée. |
| [`phil/form-logic`](https://github.com/parler-haut-interagir-librement/form-logic) | Une logique conditionnelle typée pour les formulaires, évaluée pareil en PHP et dans un contrôleur Stimulus, le serveur ayant le dernier mot, en sous-ensemble strict de JsonLogic. |
| [`phil/forms-bundle`](https://github.com/parler-haut-interagir-librement/forms-bundle) | Des formulaires définis en base : une page pour chacun, protégée du pourriel, des questions posées sous condition, des réponses gardées le temps nécessaire, envoyées par courriel ou à un webhook signé. |
| [`phil/upload-scan-bundle`](https://github.com/parler-haut-interagir-librement/upload-scan-bundle) | Les téléversements passés à l'antivirus dans Symfony : une contrainte, une quarantaine hors de la racine web, une page d'administration, des analyses sur un worker et un contrôle de santé. |
| [`phil/profile-secure-input`](https://github.com/parler-haut-interagir-librement/profile-secure-input) | Un seul require pour les paquets qui gardent ce que les usagers envoient : adresses, requêtes sortantes, débit des requêtes, pourriel des formulaires et fichiers téléversés. |

### Argent, téléphones et dates

| Paquet | Description |
|---|---|
| [`phil/money`](https://github.com/parler-haut-interagir-librement/money) | Des montants d'argent sous forme d'entiers exacts dans une devise connue, sans aucun nombre à virgule flottante. |
| [`phil/money-bundle`](https://github.com/parler-haut-interagir-librement/money-bundle) | Des montants d'argent dans les formulaires, la validation, les gabarits Twig et les entités Doctrine de Symfony, sans nombres à virgule flottante. |
| [`phil/pricing`](https://github.com/parler-haut-interagir-librement/pricing) | Fixez les prix à partir du coût sans erreurs de virgule flottante : marge et majoration, prix tirés d'une marge visée, remises en cascade, marge combinée et prix terminés en ,99. |
| [`phil/phone`](https://github.com/parler-haut-interagir-librement/phone) | Des numéros de téléphone lus comme les gens les saisissent, vérifiés selon le plan de leur pays, puis formatés. |
| [`phil/phone-bundle`](https://github.com/parler-haut-interagir-librement/phone-bundle) | Un champ de numéro de téléphone, une contrainte de validation, un type de colonne et des filtres Twig pour Symfony. |
| [`phil/recurrence`](https://github.com/parler-haut-interagir-librement/recurrence) | Des dates qui se répètent selon une règle RFC 5545 : lisez-la, vérifiez-la, écrivez-la, listez ses occurrences. |
| [`phil/recurrence-bundle`](https://github.com/parler-haut-interagir-librement/recurrence-bundle) | Un formulaire pour les événements qui se répètent, une colonne pour enregistrer la règle, et la règle en mots. |
| [`phil/tax`](https://github.com/parler-haut-interagir-librement/tax) | Plusieurs taxes sur une même vente, chacune calculée sur la même base et détaillée au cent près. |
| [`phil/opening-hours`](https://github.com/parler-haut-interagir-librement/opening-hours) | Les heures d'ouverture d'un lieu, et s'il est ouvert en ce moment. |
| [`phil/opening-hours-bundle`](https://github.com/parler-haut-interagir-librement/opening-hours-bundle) | Un formulaire d'heures d'ouverture, une colonne JSON et des heures localisées dans Twig, pour Symfony. |
| [`phil/almanac`](https://github.com/parler-haut-interagir-librement/almanac) | Les dates qui se calculent : Pâques, équinoxes, phases de la Lune, éclipses, lever et coucher du soleil, et les règles des fêtes. |
| [`phil/almanac-bundle`](https://github.com/parler-haut-interagir-librement/almanac-bundle) | Les dates calculées dans Doctrine, les formulaires et Twig, avec les lieux dont elles suivent le soleil et le ciel. |
| [`phil/calendar-sync`](https://github.com/parler-haut-interagir-librement/calendar-sync) | Les plages occupées lues dans les flux iCalendar, CalDAV, Google Agenda et Microsoft Graph, et les événements d'un site écrits chez eux. |
| [`phil/measurement`](https://github.com/parler-haut-interagir-librement/measurement) | Enregistrez, convertissez et affichez les unités de mesure exactement, températures comprises, avec les formats CLDR et les unités qu'emploie chaque canal de vente. |
| [`phil/measurement-bundle`](https://github.com/parler-haut-interagir-librement/measurement-bundle) | Les unités de mesure dans Symfony : types de formulaire, contraintes de validation, une colonne Doctrine et un formatage Twig, par-dessus phil/measurement. |

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
| [`phil/map-bundle`](https://github.com/parler-haut-interagir-librement/map-bundle) | Des cartes sur Symfony UX Map et Leaflet qu'aucun serveur de tuiles ne piste : tuiles PMTiles relayées ou hébergées chez vous, lieux en marqueurs, zones en grille de vie privée, plans de salle des kiosques. |
| [`phil/geo-bundle`](https://github.com/parler-haut-interagir-librement/geo-bundle) | Des coordonnées stockées, des distances dans Twig, des géocodeurs configurés et des listes triées du plus proche au plus loin pour les applications Symfony. |
| [`phil/contact`](https://github.com/parler-haut-interagir-librement/contact) | Des fiches de contact : noms ordonnés selon leur culture, courriels, lignes téléphoniques et identifiants, écrits en vCard, JSContact et JSON-LD. |
| [`phil/contact-bundle`](https://github.com/parler-haut-interagir-librement/contact-bundle) | Un formulaire de fiche de contact, une colonne JSON, des téléchargements vCard et JSContact, et les noms de personnes dans Twig, pour Symfony. |
| [`phil/short-link-bundle`](https://github.com/parler-haut-interagir-librement/short-link-bundle) | Des liens courts sur vos propres domaines, avec codes QR, dates et plafond de visites, et des visites comptées sans aucune donnée du visiteur. |

### Site et navigation

| Paquet | Description |
|---|---|
| [`phil/menu-bundle`](https://github.com/parler-haut-interagir-librement/menu-bundle) | Des menus de navigation et des fils d'Ariane pour Symfony, avec la page courante du visiteur marquée. |
| [`phil/redirects-bundle`](https://github.com/parler-haut-interagir-librement/redirects-bundle) | Des redirections pour Symfony, gérées depuis une administration et résolues seulement quand rien d'autre n'a répondu. |
| [`phil/sitemap`](https://github.com/parler-haut-interagir-librement/sitemap) | Le plan de site XML des moteurs de recherche, construit à partir de pages vérifiées et coupé aux limites du protocole. |
| [`phil/sitemap-bundle`](https://github.com/parler-haut-interagir-librement/sitemap-bundle) | `/sitemap.xml` pour un site Symfony, à partir d'options de route, de fournisseurs d'URL et d'un événement. |
| [`phil/feed-bundle`](https://github.com/parler-haut-interagir-librement/feed-bundle) | Servez des flux Atom, RSS et JSON Feed pour les nouvelles, les contenus ou les événements à partir d'une seule liste, liés depuis les pages, mis en cache par HTTP et annoncés aux hubs WebSub. |
| [`phil/link-checker`](https://github.com/parler-haut-interagir-librement/link-checker) | Trouvez les liens morts du contenu d'un site avant les lecteurs : chaque adresse vérifiée une fois, à un rythme mesuré par hôte, ancres vérifiées, réseaux privés refusés. |
| [`phil/seo-check`](https://github.com/parler-haut-interagir-librement/seo-check) | Trouvez ce qui tient une page hors des résultats de recherche quand elle est publiée : titre, description, langue, URL canonique, indexation, hreflang, liens et images. |
| [`phil/structured-data`](https://github.com/parler-haut-interagir-librement/structured-data) | Le graphe schema.org d'une page sous forme de valeurs : des nœuds venus de toutes les parties d'un site, fusionnés par `@id`, vérifiés, puis imprimés dans un seul élément de script JSON-LD. |
| [`phil/well-known-bundle`](https://github.com/parler-haut-interagir-librement/well-known-bundle) | Les adresses `/.well-known/` d'un site Symfony, chacune activée dans sa configuration. |
| [`phil/http-cache-tags-bundle`](https://github.com/parler-haut-interagir-librement/http-cache-tags-bundle) | Mettez des pages entières en cache dans Varnish ou un CDN, et purgez exactement celles qu'une modification change. |
| [`phil/structured-data-bundle`](https://github.com/parler-haut-interagir-librement/structured-data-bundle) | Le graphe schema.org de chaque page dans Symfony, écrit dans un seul élément script JSON-LD, jamais pour une page tenue à l'écart des moteurs de recherche. |
| [`phil/social-cards`](https://github.com/parler-haut-interagir-librement/social-cards) | Les balises Open Graph, X et du fédivers, des cartes de partage dessinées d'après un gabarit, et les balises des pages liées lues et vérifiées. |
| [`phil/social-cards-bundle`](https://github.com/parler-haut-interagir-librement/social-cards-bundle) | Les balises de partage de chaque page, des cartes de partage dessinées par un worker, et des aperçus sûrs des liens que collent les membres. |
| [`phil/embed-bundle`](https://github.com/parler-haut-interagir-librement/embed-bundle) | Des fonctions que d'autres sites intègrent : un jeton signé pour une origine, frame-ancestors pour elle seule, et un lien simple sans JavaScript. |
| [`phil/pwa-bundle`](https://github.com/parler-haut-interagir-librement/pwa-bundle) | Un site installable qui se lit hors ligne, sans Workbox : manifeste, service worker, page hors ligne et second écran. |

### Communauté et événements

| Paquet | Description |
|---|---|
| [`phil/poll`](https://github.com/parler-haut-interagir-librement/poll) | Un moteur de vote : des bulletins vérifiés selon les règles de leur scrutin, puis dépouillés en classement. |
| [`phil/reservation`](https://github.com/parler-haut-interagir-librement/reservation) | Le cœur des réservations : une ressource, ses créneaux, ses règles, ses conflits et ses rappels. |
| [`phil/calendar-bundle`](https://github.com/parler-haut-interagir-librement/calendar-bundle) | Des événements avec récurrence, lieux et hôtes dans Symfony : occurrences développées dans un budget, vues du mois à l'affiche dans un composant Live, iCalendar, flux privés. |
| [`phil/reservation-bundle`](https://github.com/parler-haut-interagir-librement/reservation-bundle) | Des ressources réservables dans Symfony : réservations retenues, confirmées, déplacées et annulées sans double réservation, plages libres, retenues qui expirent et rappels par Messenger. |
| [`phil/checkin`](https://github.com/parler-haut-interagir-librement/checkin) | L'enregistrement à l'entrée d'un événement ou d'un lieu : un code lu à une porte, décidé aussitôt, enregistré une fois, en ligne ou hors ligne. |
| [`phil/listings`](https://github.com/parler-haut-interagir-librement/listings) | Le noyau des annonces : une petite annonce, une offre d'emploi, une recherche de partenaires, rédigées, modérées, reçoivent des réponses et expirent selon les mêmes règles. |
| [`phil/open-badges`](https://github.com/parler-haut-interagir-librement/open-badges) | Des Open Badges 3.0 que tout le monde peut vérifier : émis, signés, intégrés à l'image, révoqués, appuyés et réunis dans un relevé d'apprentissage. |
| [`phil/directory-bundle`](https://github.com/parler-haut-interagir-librement/directory-bundle) | Un répertoire de fiches avec leur historique, leurs éditeurs et leurs liens, chacune trouvée comme son auteur le décide. |
| [`phil/checkin-bundle`](https://github.com/parler-haut-interagir-librement/checkin-bundle) | L'accueil dans Symfony : points de lecture, de manifeste et de synchronisation, et un poste qui continue hors ligne. |
| [`phil/comments-bundle`](https://github.com/parler-haut-interagir-librement/comments-bundle) | Des commentaires sur une page entière ou sur un passage d'un texte, avec des ancres qui suivent les modifications et des remplacements proposés. |
| [`phil/community-notes`](https://github.com/parler-haut-interagir-librement/community-notes) | Des notes communautaires écrites par des membres qualifiés et publiées par une règle d'accord croisé explicable. |
| [`phil/poll-bundle`](https://github.com/parler-haut-interagir-librement/poll-bundle) | Scrutins et bulletins dans Symfony : bulletins anonymes modifiés par reçu, et vote unique transférable par la méthode de Meek avec ses tours. |
| [`phil/survey-bundle`](https://github.com/parler-haut-interagir-librement/survey-bundle) | Menez des sondages qui s'adaptent à chaque réponse : sections conditionnelles, questions de classement et en matrice, quotas croisés, anonymes par défaut, résultats affichés au-delà d'un seuil. |
| [`phil/scheduling-poll-bundle`](https://github.com/parler-haut-interagir-librement/scheduling-poll-bundle) | Trouvez une date qui convient à tous : dates proposées répondues oui, si nécessaire ou non par lien personnel, sans compte ; meilleure date choisie, iCalendar envoyé par courriel à tous. |
| [`phil/stockroom`](https://github.com/parler-haut-interagir-librement/stockroom) | Un magasin d'accessoires : contenants imbriqués, objets comptés ou suivis un à un, journal de chaque mouvement, listes de chargement vérifiées au retour. |
| [`phil/notifications-bundle`](https://github.com/parler-haut-interagir-librement/notifications-bundle) | Des notifications sur Symfony Notifier, remises sur les canaux que chacun a choisis : centre de notifications et toasts, courriel et son résumé, Web Push, Slack ou Discord. |
| [`phil/follow-bundle`](https://github.com/parler-haut-interagir-librement/follow-bundle) | Soyez avisé de l'activité nouvelle sur ce que vous suivez : contenus, fils, annonces ou membres, au niveau que vous choisissez, par des notifications ; demandes et liens réciproques. |
| [`phil/conversation-bundle`](https://github.com/parler-haut-interagir-librement/conversation-bundle) | Des messages privés dans Symfony, scellés au repos : conversations directes et de groupe, qui peut écrire à qui, blocages, lu et non lu, notifications et Mercure facultatifs. |
| [`phil/helpdesk-bundle`](https://github.com/parler-haut-interagir-librement/helpdesk-bundle) | Un service d'assistance dans Symfony où chaque demande est une conversation privée : files traitées par rôle, statuts menés par Symfony Workflow, responsables et réponses toutes faites. |
| [`phil/mentions-bundle`](https://github.com/parler-haut-interagir-librement/mentions-bundle) | Des @mentions de membres en texte, en Markdown et dans les documents UJO, seuls les nouveaux mentionnés qui peuvent voir le contenu étant avisés, avec une autocomplétion. |
| [`phil/onboarding-bundle`](https://github.com/parler-haut-interagir-librement/onboarding-bundle) | L'accueil d'un nouveau membre : des parcours d'étapes ordonnées, la progression de chacun, une liste de contrôle et une visite guidée accessibles. |
| [`phil/open-badges-bundle`](https://github.com/parler-haut-interagir-librement/open-badges-bundle) | Open Badges dans Symfony : l'émetteur et sa clé par la configuration, son profil, ses clés et ses listes de statut servis, et une page de vérification des jetons, documents et images. |
| [`phil/certificates-bundle`](https://github.com/parler-haut-interagir-librement/certificates-bundle) | Des attestations de réussite sur Open Badges : cours et critères, attestation PDF avec code QR de vérification et justificatif signé, registre public et révocation. |
| [`phil/stockroom-bundle`](https://github.com/parler-haut-interagir-librement/stockroom-bundle) | Le magasin d'accessoires dans Symfony : contenants, objets, journal et listes de chargement dans des tables Doctrine DBAL, mouvements dans une transaction qui verrouille les objets. |
| [`phil/moderation-bundle`](https://github.com/parler-haut-interagir-librement/moderation-bundle) | Une seule file de signalements pour les messages, commentaires, réactions, annonces, messages privés et profils : rôles, historique des décisions, avis motivé aux auteurs et journal public sans noms. |
| [`phil/newsletter-bundle`](https://github.com/parler-haut-interagir-librement/newsletter-bundle) | L'infolettre d'un site : listes, double confirmation, campagnes en Markdown, envoi au rythme des profils d'expéditeur, désabonnement en un clic, centre de préférences et retours lus. |
| [`phil/newsletter-archive`](https://github.com/parler-haut-interagir-librement/newsletter-archive) | Mettez les infolettres envoyées sur le Web sans exposer les abonnés : numéros publiés par liste et par étiquette, liens de partage révocables, versions Web sans renseignements personnels. |
| [`phil/reactions-bundle`](https://github.com/parler-haut-interagir-librement/reactions-bundle) | Des réactions sur tout contenu : émojis Unicode et ceux du site, un sélecteur accessible, un Live Component qui marche aussi en simple formulaire, et des compteurs réglés par chaque site. |
| [`phil/reviews-bundle`](https://github.com/parler-haut-interagir-librement/reviews-bundle) | Des avis et des notes fiables pour les produits, les jeux ou les lieux : un par membre, notes par critère, votes d'utilité, réponses publiques, retrait selon des règles énoncées. |
| [`phil/avatar`](https://github.com/parler-haut-interagir-librement/avatar) | Des avatars pour les membres sans photo : un SVG tiré d'une graine opaque, jamais d'une adresse courriel, en initiales au contraste WCAG AA, en formes ou en grilles de pixels, déterministe. |
| [`phil/habits`](https://github.com/parler-haut-interagir-librement/habits) | Le suivi d'habitudes qui ne punit pas : horaires dans le fuseau du membre, pointages faits, sautés ou partiels, séries indulgentes, une force de 0 à 100 %, stockage DBAL. |
| [`phil/assignment`](https://github.com/parler-haut-interagir-librement/assignment) | Place des personnes selon leurs préférences classées (tables de jeu, ateliers, chambres, rôles) : rondes de choix ou optimum à coût minimal, avec graine, reproductible, chaque placement expliqué. |
| [`phil/proposal-review`](https://github.com/parler-haut-interagir-librement/proposal-review) | L'évaluation par un jury des appels à propositions et des concours : phases datées, critères pondérés, évaluation à l'aveugle avec conflits d'intérêts, décisions en lot, votes par paires, stockage DBAL. |
| [`phil/event-weather`](https://github.com/parler-haut-interagir-librement/event-weather) | La météo prévue au lieu et à l'heure d'un événement extérieur : Environnement Canada et ses alertes au Canada, MET Norway ailleurs, chaque fournisseur cité. |
| [`phil/library-bundle`](https://github.com/parler-haut-interagir-librement/library-bundle) | Les prêts d'une ludothèque ou d'une bibliothèque dans Symfony : un catalogue cherché sans JavaScript, des exemplaires prêtés, réservés, renouvelés et rendus, des rappels de retard par Messenger. |
| [`phil/shifts`](https://github.com/parler-haut-interagir-librement/shifts) | Des quarts de bénévoles sans double inscription : quarts fixes, par tâche ou par durée, inscription, affectation ou répartition selon les intérêts, une réserve et un tableau d'échanges. |
| [`phil/audience-segments`](https://github.com/parler-haut-interagir-librement/audience-segments) | Choisissez qui reçoit un message par des règles JSON lisibles sur les billets, les achats, le soutien et les étiquettes, avec des listes de destinataires figées et des exclusions globales, sans profilage. |
| [`phil/benefit-deliverables`](https://github.com/parler-haut-interagir-librement/benefit-deliverables) | Suivez les avantages dus à chaque membre par période comme une liste à cocher, avec des exports d'expédition de courte durée, pour les abonnements, les adhésions et les dons. |
| [`phil/code-of-conduct`](https://github.com/parler-haut-interagir-librement/code-of-conduct) | Publiez un code de conduite versionné, consignez qui a accepté quelle version, et acheminez les signalements chiffrés aux responsables qui n'y sont pas nommés. |
| [`phil/participant-safety-info`](https://github.com/parler-haut-interagir-librement/participant-safety-info) | Gardez chiffrés les contacts d'urgence, les allergies et les besoins d'un participant pour un événement, lisibles par ses seuls responsables, chaque lecture journalisée. |
| [`phil/shifts-bundle`](https://github.com/parler-haut-interagir-librement/shifts-bundle) | Les quarts de bénévoles dans Symfony : horaires et tableau d'échanges enregistrés par Doctrine, pages des bénévoles et des coordonnateurs, un formulaire de quart, des composants Twig, Messenger. |
| [`phil/waitlist-bundle`](https://github.com/parler-haut-interagir-librement/waitlist-bundle) | Les séances, ateliers et événements complets perdent les places libérées par les annulations : une liste d'attente les offre à tour de rôle, avec un délai, et passe les offres échues au suivant. |
| [`phil/workshops-bundle`](https://github.com/parler-haut-interagir-librement/workshops-bundle) | Les séances d'atelier dans Symfony : places prises sans surréservation, inscriptions en double ignorées, listes d'attente qui offrent à tour de rôle les places libérées. |

### Impression et produits

| Paquet | Description |
|---|---|
| [`phil/fonts`](https://github.com/parler-haut-interagir-librement/fonts) | Ce qu'un fichier de police dit de lui-même (ses noms, sa graisse, sa licence d'incorporation) et la règle `@font-face` pour le servir. |
| [`phil/print-preflight`](https://github.com/parler-haut-interagir-librement/print-preflight) | Ce qu'un imprimeur refuserait, avant que le fichier lui parvienne. |
| [`phil/print-compositor`](https://github.com/parler-haut-interagir-librement/print-compositor) | Les maquettes que montre une boutique et les fichiers d'impression que reçoit un imprimeur, dessinés à partir des mêmes calques. |
| [`phil/commerce-product-template`](https://github.com/parler-haut-interagir-librement/commerce-product-template) | Un gabarit et un visuel donnent chaque produit d'un catalogue : chaque UGS, son prix, ses canaux et ses illustrations. |
| [`phil/paged-media`](https://github.com/parler-haut-interagir-librement/paged-media) | Des documents PDF derrière une seule API : un écrivain natif sans dépendance, des champs de formulaire, des imprimés et un client Gotenberg. |
| [`phil/paged-media-bundle`](https://github.com/parler-haut-interagir-librement/paged-media-bundle) | L'écrivain PDF natif et Gotenberg derrière un seul service, des gabarits par attribut et le rendu asynchrone par Messenger. |
| [`phil/paged-media-weasyprint`](https://github.com/parler-haut-interagir-librement/paged-media-weasyprint) | Du HTML et du CSS mis en page en PDF par WeasyPrint, sans accès au réseau et avec les fichiers d'un seul dossier. |
| [`phil/fonts-bundle`](https://github.com/parler-haut-interagir-librement/fonts-bundle) | Un dossier de polices lu en catalogue, le CSS @font-face et un registre des licences vérifié par une commande. |
| [`phil/print-preflight-bundle`](https://github.com/parler-haut-interagir-librement/print-preflight-bundle) | Des profils d'imprimeur tirés de la configuration, des vérifications ajoutées par attribut, et les documents vérifiés avant l'impression. |
| [`phil/commerce-bundle`](https://github.com/parler-haut-interagir-librement/commerce-bundle) | Le noyau du commerce dans Symfony : commandes et ajustements, canaux, taxes par ligne, paiements aux notifications vérifiées, reçus et notes de crédit numérotés sans trou avec leur facture PDF. |
| [`phil/commerce-catalog-bundle`](https://github.com/parler-haut-interagir-librement/commerce-catalog-bundle) | Produits, variantes et grilles de prix pour une boutique Symfony sur phil/commerce-bundle : familles, produits composés, noms traduits, catégories, prix libre. |
| [`phil/commerce-product-template-bundle`](https://github.com/parler-haut-interagir-librement/commerce-product-template-bundle) | Chaque produit d'un motif sur un modèle, généré dans le catalogue Symfony, avec ses maquettes, ses fichiers d'impression et les commandes de production pour l'imprimeur. |
| [`phil/commerce-promotions-bundle`](https://github.com/parler-haut-interagir-librement/commerce-promotions-bundle) | Des promotions pour une boutique Symfony sur phil/commerce-bundle : pourcentage ou montant de rabais, N pour X, prix de lot, livraison gratuite, codes en séries, utilisations par client. |
| [`phil/commerce-logistics-bundle`](https://github.com/parler-haut-interagir-librement/commerce-logistics-bundle) | Stock, expéditions et livraison pour une boutique Symfony sur phil/commerce-bundle : chaque mouvement journalisé, réservations, tarifs et étiquettes des transporteurs, stock de congrès. |
| [`phil/commerce-consignment-bundle`](https://github.com/parler-haut-interagir-librement/commerce-consignment-bundle) | Vendez des biens en consignation à côté des vôtres sous une seule UGS (unité de gestion de stock) : à qui appartient chaque unité, laquelle part la première, la marge ou la commission de chaque vente, relevés et versements. |
| [`phil/inventory-alerts`](https://github.com/parler-haut-interagir-librement/inventory-alerts) | Sachez quand recommander et qui prévenir : points de commande, stock de sécurité, min/max et quantité économique, alertes de stock bas par emplacement, alertes de retour en stock et de baisse de prix sur consentement. |
| [`phil/commerce-pos-bundle`](https://github.com/parler-haut-interagir-librement/commerce-pos-bundle) | Une caisse de boutique dans Symfony sur phil/commerce-bundle : tiroirs-caisses, sessions de caisse et rapports de fin de journée, terminaux connectés, mises de côté, et ventes enregistrées hors ligne. |
| [`phil/commerce-stored-value-bundle`](https://github.com/parler-haut-interagir-librement/commerce-stored-value-bundle) | Cartes-cadeaux et crédit en magasin pour une boutique Symfony sur phil/commerce-bundle : vendus, émis, rechargés et dépensés comme paiements, avec une carte imprimée. |
| [`phil/commerce-returns-bundle`](https://github.com/parler-haut-interagir-librement/commerce-returns-bundle) | Les retours pour une boutique Symfony sur phil/commerce-bundle : demandes autorisées, reçues, inspectées, articles remis en stock, remboursements, crédit en magasin, échanges, notes de crédit. |
| [`phil/commerce-reports-bundle`](https://github.com/parler-haut-interagir-librement/commerce-reports-bundle) | Sachez ce qui se vend et ce qui dort sur les tablettes : ventes brutes et nettes par produit, catégorie, canal, emplacement et caissier, rapports d'écoulement et de stock mort. |
| [`phil/commerce-subscription-bundle`](https://github.com/parler-haut-interagir-librement/commerce-subscription-bundle) | Des abonnements dans Symfony sur phil/commerce-bundle : paliers, périodes de facturation, essais, renouvellement et relances de paiement, prorata, cadeaux, places, les fonctionnalités que chaque palier débloque. |
| [`phil/commerce-wholesale-bundle`](https://github.com/parler-haut-interagir-librement/commerce-wholesale-bundle) | La vente en gros pour une boutique Symfony sur phil/commerce-bundle : groupes de clients avec leurs prix et leurs minimums, règles de quantité par UGS, ventes à crédit avec conditions nettes. |
| [`phil/commerce-syndication-bundle`](https://github.com/parler-haut-interagir-librement/commerce-syndication-bundle) | Le catalogue d'un fournisseur vendu par d'autres boutiques, dans Symfony : prix et stock signés transmis aux revendeurs, commandes relayées et expédiées, retours crédités, un seul grand livre. |
| [`phil/product-feeds`](https://github.com/parler-haut-interagir-librement/product-feeds) | Des flux de produits pour les comparateurs et les places de marché, écrits en mémoire constante en RSS avec données de produits ou en TSV, et mis en place d'un seul coup. |
| [`phil/licensing-bundle`](https://github.com/parler-haut-interagir-librement/licensing-bundle) | Les licences des œuvres et des produits : ce que chacune exige, des pages d'attribution en HTML, UJO ou JSON-LD, et une vérification avant l'export ou l'impression. |
| [`phil/receipt-email`](https://github.com/parler-haut-interagir-librement/receipt-email) | Envoyez par courriel à chaque paiement son reçu numéroté et à chaque don son reçu fiscal officiel : montants tels qu'émis, une partie en texte brut, le PDF sur demande, aucun suivi. |
| [`phil/local-pickup`](https://github.com/parler-haut-interagir-librement/local-pickup) | Laissez les clients récupérer leurs commandes en magasin ou au kiosque d'un congrès : créneaux avec capacité, avis de commande prête, codes de comptoir à usage unique avec QR, délais de garde et expiration. |
| [`phil/product-bundles`](https://github.com/parler-haut-interagir-librement/product-bundles) | Vendez des produits en un seul lot : prix fixe ou réduit, stock fixé par la pièce la plus rare, coût composé de ses pièces, retours complets, lots partagés entre vendeurs. |

### Contenu et recherche

| Paquet | Description |
|---|---|
| [`phil/content-bundle`](https://github.com/parler-haut-interagir-librement/content-bundle) | Du contenu par langue, étape et version : brouillons publiés par un workflow, corps publiés en UJO, routes qui gardent les anciens slugs, et une administration. |
| [`phil/locale-dimensions`](https://github.com/parler-haut-interagir-librement/locale-dimensions) | Les langues, les versions et les chaînes de repli entre langues, en objets-valeurs. |
| [`phil/publishing`](https://github.com/parler-haut-interagir-librement/publishing) | Les événements qu'envoie une page quand elle est mise en ligne ou retirée, pour ceux qui la suivent. |
| [`phil/scheduled-transitions-bundle`](https://github.com/parler-haut-interagir-librement/scheduled-transitions-bundle) | Publier ou dépublier à une date : des transitions de workflow planifiées et lancées par le Scheduler de Symfony. |
| [`phil/doctrine-behaviours`](https://github.com/parler-haut-interagir-librement/doctrine-behaviours) | Des comportements Doctrine : horodatage, auteurs, corbeille, arbres, slugs, positions et traductions. |
| [`phil/doctrine-behaviours-bundle`](https://github.com/parler-haut-interagir-librement/doctrine-behaviours-bundle) | Les comportements Doctrine dans Symfony : écouteurs activés par la configuration, auteur et propriétaire tirés de la personne connectée, arbres, positions, traductions, versions et leur restauration protégée. |
| [`phil/search-bundle`](https://github.com/parler-haut-interagir-librement/search-bundle) | La recherche avec facettes sur la base de données, Meilisearch ou Loupe, des synonymes par langue, et des filtres qui marchent sans JavaScript. |
| [`phil/search-loupe`](https://github.com/parler-haut-interagir-librement/search-loupe) | L'adaptateur Loupe de `phil/search-bundle` : des index de recherche dans des fichiers SQLite, sans serveur à faire tourner. |
| [`phil/search-synonyms`](https://github.com/parler-haut-interagir-librement/search-synonyms) | Des règles de synonymes par langue, vérifiées pour leurs chevauchements, lues et écrites aux formats Solr et JSON. |
| [`phil/translation-memory`](https://github.com/parler-haut-interagir-librement/translation-memory) | La traduction par segments : une mémoire de traduction, un glossaire, des contrôles de qualité, et les échanges XLIFF, TMX et TBX. |
| [`phil/translation-bundle`](https://github.com/parler-haut-interagir-librement/translation-bundle) | La traduction par segments dans Symfony : un écran de traduction, une mémoire et des glossaires dans Doctrine, des révisions et les échanges XLIFF. |
| [`phil/media-bundle`](https://github.com/parler-haut-interagir-librement/media-bundle) | Une médiathèque pour Symfony : des images autour de leur point focal en WebP ou AVIF, des SVG purifiés, des composants adaptatifs et un explorateur de fichiers. |
| [`phil/audio`](https://github.com/parler-haut-interagir-librement/audio) | De l'audio derrière un port : transcodage par ffmpeg, métadonnées, crêtes de la forme d'onde calculées sur le serveur, extraits, politique d'écoute du créateur, chapitres et transcriptions en texte minuté. |
| [`phil/audio-bundle`](https://github.com/parler-haut-interagir-librement/audio-bundle) | L'audio dans Symfony : versions et formes d'onde produites par des tâches Messenger, téléversements par la médiathèque, et un composant de lecteur accessible. |
| [`phil/video-bundle`](https://github.com/parler-haut-interagir-librement/video-bundle) | Des vidéos dans Symfony : fichiers hébergés ou HLS dans l'élément vidéo du navigateur, YouTube et Vimeo derrière une façade sans pistage, sous-titres, chapitres et transcriptions, progression comptée seulement avec consentement. |
| [`phil/transcription`](https://github.com/parler-haut-interagir-librement/transcription) | La transcription des enregistrements derrière un port : un serveur whisper.cpp tenu par le site, des adaptateurs infonuagiques refusés sans le consentement des personnes entendues, et une relecture avant publication. |
| [`phil/editorial-calendar`](https://github.com/parler-haut-interagir-librement/editorial-calendar) | Planifier les publications : éléments par contenu, langue et auteur dans leur flux de travail, lots tout ou rien, vues de calendrier, conflits et trous, export iCalendar. |
| [`phil/faq-bundle`](https://github.com/parler-haut-interagir-librement/faq-bundle) | Les questions fréquentes par section, cherchables sans JavaScript, avec des ancres stables, des votes anonymes et les données structurées FAQPage. |
| [`phil/content-access`](https://github.com/parler-haut-interagir-librement/content-access) | Décidez qui lit un contenu : public, membres, paliers par règles, accès anticipé puis public, accès progressif et glissant, établissements ; parties réservées jamais envoyées. |
| [`phil/related-content`](https://github.com/parler-haut-interagir-librement/related-content) | Montrez les pages et produits apparentés sans profiler les lecteurs : termes communs pondérés, épingles manuelles et accessoires, achats conjoints comptés par paire, jamais par personne. |

### Vie privée, sécurité et accessibilité

| Paquet | Description |
|---|---|
| [`phil/accessibility-check`](https://github.com/parler-haut-interagir-librement/accessibility-check) | Vérifier l'accessibilité d'un HTML ou d'un document UJO avant sa publication : contrastes, titres, langues, images. |
| [`phil/accessibility-statement`](https://github.com/parler-haut-interagir-librement/accessibility-statement) | Une déclaration d'accessibilité écrite à partir du rapport d'une vérification, en anglais et en français. |
| [`phil/creation-disclosure`](https://github.com/parler-haut-interagir-librement/creation-disclosure) | Dire comment une œuvre a été faite, IA générative comprise, dans le vocabulaire Digital Source Type de l'IPTC. |
| [`phil/indexing-consent`](https://github.com/parler-haut-interagir-librement/indexing-consent) | Si la page d'un membre peut être indexée : listée, non listée ou privée, lu par le plan du site et les données structurées. |
| [`phil/ip-anonymizer`](https://github.com/parler-haut-interagir-librement/ip-anonymizer) | Des adresses IP tronquées, hachées sous une clé ou oubliées avec leur sel, avant que rien ne les garde. |
| [`phil/analytics-bundle`](https://github.com/parler-haut-interagir-librement/analytics-bundle) | Une analytique sans identifiant de visiteur : des compteurs par jour, les petits nombres masqués, des rapports et des alertes. |
| [`phil/broken-flow-alerts`](https://github.com/parler-haut-interagir-librement/broken-flow-alerts) | Une alerte quand une inscription ou un achat cesse de se compléter, selon le rythme habituel de la semaine. |
| [`phil/identity-client-bundle`](https://github.com/parler-haut-interagir-librement/identity-client-bundle) | Un client Symfony d'un fournisseur OpenID Connect : déconnexion par canal arrière, authentification renforcée et rafraîchissements sérialisés. |
| [`phil/identity-provider-bundle`](https://github.com/parler-haut-interagir-librement/identity-provider-bundle) | Un fournisseur d'identité ouvert, un module par méthode : comptes vérifiés, mots de passe vérifiés contre les fuites, clés d'accès, TOTP, liens de connexion, réauthentification et OpenID Connect avec PKCE et clés tournantes. |
| [`phil/identity-provider-google-bundle`](https://github.com/parler-haut-interagir-librement/identity-provider-google-bundle) | Laissez les gens se connecter avec Google et le lier à leur compte en toute sécurité : connexion Google pour phil/identity-provider-bundle, avec les événements de Cross-Account Protection. |
| [`phil/saml-sp`](https://github.com/parler-haut-interagir-librement/saml-sp) | Laissez les gens se connecter avec le compte de leur organisation : un fournisseur de services SAML 2.0 pour PHP qui vérifie les assertions signées et les associe aux utilisateurs. |
| [`phil/saml-sp-bundle`](https://github.com/parler-haut-interagir-librement/saml-sp-bundle) | Laissez les gens se connecter avec le compte de leur organisation dans Symfony : authentification unique SAML 2.0, un fournisseur d'identité par organisation, comptes créés à la première connexion. |
| [`phil/email-address`](https://github.com/parler-haut-interagir-librement/email-address) | Des adresses de courriel qu'un serveur peut livrer, avec des suggestions pour les fautes comme gmial.com. |
| [`phil/email-address-bundle`](https://github.com/parler-haut-interagir-librement/email-address-bundle) | Repère dans les formulaires les adresses de courriel mal tapées, jetables ou impossibles à livrer, et change les rebonds et les plaintes en décisions de suspension, de désabonnement ou d'examen. |
| [`phil/mail-auth-check`](https://github.com/parler-haut-interagir-librement/mail-auth-check) | Sachez avant l'envoi si les destinataires feront confiance à votre courriel : SPF et sa limite de dix requêtes, DKIM et DMARC vérifiés selon un profil d'expéditeur. |
| [`phil/rate-limit`](https://github.com/parler-haut-interagir-librement/rate-limit) | Une garde de construction qui refuse, en production, une limite de débit comptée à part sur chaque serveur. |
| [`phil/rate-limit-bundle`](https://github.com/parler-haut-interagir-librement/rate-limit-bundle) | Des limites de débit déclarées sur les contrôleurs, les routes et les formulaires, avec des réponses 429 et les en-têtes RateLimit. |
| [`phil/usage-meter`](https://github.com/parler-haut-interagir-librement/usage-meter) | Des quotas qui refusent une action avant qu'elle dépasse sa limite : compteurs par sujet sur des mois civils, des périodes de facturation ou des fenêtres glissantes. |
| [`phil/usage-meter-bundle`](https://github.com/parler-haut-interagir-librement/usage-meter-bundle) | Les quotas dans Symfony : compteurs déclarés dans la configuration, décomptes dans la base de données, limites tirées de fournisseurs ou d'abonnements, limiteurs de débit sur un compteur. |
| [`phil/safe-http`](https://github.com/parler-haut-interagir-librement/safe-http) | Lire les adresses que donnent les membres ou d'autres sites sans atteindre votre propre réseau : redirections vérifiées une à une. |
| [`phil/idempotency`](https://github.com/parler-haut-interagir-librement/idempotency) | Exécuter une opération une fois par clé d'idempotence, rejouer sa réponse, et traiter une fois chaque message de Messenger. |
| [`phil/browser-security-bundle`](https://github.com/parler-haut-interagir-librement/browser-security-bundle) | La Content Security Policy avec ses nonces, HSTS, des cookies signés et les autres protections qu'impose un navigateur, page par page. |
| [`phil/cors-bundle`](https://github.com/parler-haut-interagir-librement/cors-bundle) | Laissez les navigateurs appeler votre API depuis les origines que vous autorisez : des règles CORS par chemin pour Symfony, la requête préalable traitée tôt, jamais de joker avec des identifiants. |
| [`phil/legal-notices`](https://github.com/parler-haut-interagir-librement/legal-notices) | Les mentions légales et la politique de confidentialité en documents versionnés, remplis à partir de l'identité de l'éditeur. |
| [`phil/legal-notices-bundle`](https://github.com/parler-haut-interagir-librement/legal-notices-bundle) | Les mentions légales et la politique de confidentialité d'un site à leurs propres adresses, dans chaque langue, chaque version gardée et liée depuis le pied de page. |
| [`phil/terms-bundle`](https://github.com/parler-haut-interagir-librement/terms-bundle) | Des conditions d'utilisation versionnées : versions publiées une fois, registre d'acceptations en ajout seul, barrière pour une nouvelle version importante, rappels et pages publiques avec leurs changements. |
| [`phil/accessibility-statement-bundle`](https://github.com/parler-haut-interagir-librement/accessibility-statement-bundle) | La déclaration d'accessibilité d'une application : versionnée, publiée à /accessibilite en français et en anglais avec le libellé des WCAG, d'EN 301 549 ou de la Loi canadienne sur l'accessibilité. |
| [`phil/resource-permissions`](https://github.com/parler-haut-interagir-librement/resource-permissions) | Permissions par ressource sur un seul modèle sujet-relation-objet : relations déclarées, droits hérités dans un arbre, vérifications et listes bornées. |
| [`phil/access-bundle`](https://github.com/parler-haut-interagir-librement/access-bundle) | Visibilité par élément, niveaux de partage qui expirent seuls, groupes d'accès et « voir comme ce membre » journalisé, décidés par des voteurs Symfony. |
| [`phil/organizations-bundle`](https://github.com/parler-haut-interagir-librement/organizations-bundle) | Les organisations dans Symfony : des membres désignés par un sujet opaque, des rôles et des équipes, des invitations limitées dans le temps et en usages, l'adhésion par un domaine de courriel vérifié, et l'action au nom d'une organisation avec l'auteur réel consigné. |
| [`phil/access-codes`](https://github.com/parler-haut-interagir-librement/access-codes) | Codes à usage unique par lots nommés, remis une seule fois et gardés en empreintes à clé, rachetés de façon atomique, essais bornés par un limiteur. |
| [`phil/access-links`](https://github.com/parler-haut-interagir-librement/access-links) | Invitations par lien personnel : jeton gardé en empreinte à clé, réponse modifiable jusqu'à la clôture, rappels aux seuls non-répondants. |
| [`phil/privacy-bundle`](https://github.com/parler-haut-interagir-librement/privacy-bundle) | Les lois sur la vie privée en code : champs personnels déclarés, journaux expurgés, export et effacement, purges de conservation, consentement aux témoins refusé par défaut, demandes par lien vérifié et copies de base anonymisées. |
| [`phil/audit-bundle`](https://github.com/parler-haut-interagir-librement/audit-bundle) | Un journal d'audit en ajout seul : lignes chaînées par empreinte, indexées par acteur, action et enregistrement, engagements salés sur les champs personnels et ancrages externes. |
| [`phil/audit-viewer`](https://github.com/parler-haut-interagir-librement/audit-viewer) | Le journal d'audit lu dans EasyAdmin : filtres, historique d'un enregistrement, état de chaque chaîne et export CSV journalisé. |
| [`phil/user-secrets-bundle`](https://github.com/parler-haut-interagir-librement/user-secrets-bundle) | Les clés d'API et jetons OAuth que les gens confient à un site : scellés par chiffrement d'enveloppe à clés tournantes, rafraîchis sous verrou, révocables et effacés avec le compte. |
| [`phil/api-keys-bundle`](https://github.com/parler-haut-interagir-librement/api-keys-bundle) | Des jetons d'accès pour une API publique : conservés en empreinte et montrés une seule fois, à portée limitée, expirants, révocables et renouvelés, vérifiés par Symfony Security. |
| [`phil/guardian-consent`](https://github.com/parler-haut-interagir-librement/guardian-consent) | Le consentement parental des membres sous l'âge du consentement : tranches d'âge par juridiction, consentement par fonction par lien courriel signé, preuves datées et retrait. |

### PHIL UX

| Paquet | Description |
|---|---|
| [`phil/ux-css`](https://github.com/parler-haut-interagir-librement/ux-css) | phil-ux, un cadre CSS presque sans classes sur des jetons `--phil-*`, clair et sombre, aux thèmes vérifiés pour le contraste. |
| [`phil/ux-kit`](https://github.com/parler-haut-interagir-librement/ux-kit) | Le kit PHIL UX du Symfony UX Toolkit : les recettes du kit Shadcn, habillées par phil-ux, copiées par `ux:install`. |
| [`phil/ux-bundle`](https://github.com/parler-haut-interagir-librement/ux-bundle) | Le thème de formulaire PHIL UX sur chaque formulaire d'une application Symfony, et la feuille de `phil/ux-css` dans AssetMapper. |
| [`phil/ux-easyadmin-bundle`](https://github.com/parler-haut-interagir-librement/ux-easyadmin-bundle) | L'administration EasyAdmin aux couleurs, coins et polices des jetons de phil-ux, en clair et en sombre. |
| [`phil/ux-kit-shadcn`](https://github.com/parler-haut-interagir-librement/ux-kit-shadcn) | Les versions Tailwind, dans le style du kit Shadcn, des recettes de plus de PHIL UX, sous les mêmes noms et props, copiées par `ux:install`. |

### Intégrations

| Paquet | Description |
|---|---|
| [`phil/standard-webhooks`](https://github.com/parler-haut-interagir-librement/standard-webhooks) | Signez et vérifiez des webhooks que n'importe quelle bibliothèque Standard Webhooks peut envoyer ou vérifier. |
| [`phil/standard-webhooks-bundle`](https://github.com/parler-haut-interagir-librement/standard-webhooks-bundle) | Des livraisons de webhooks vérifiées en arguments de contrôleur, et des webhooks signés envoyés depuis Symfony. |
| [`phil/circuit-breaker`](https://github.com/parler-haut-interagir-librement/circuit-breaker) | Un disjoncteur par tiers : les appels cessent aussitôt quand il échoue sans arrêt, et reprennent quand il se rétablit. |
| [`phil/circuit-breaker-bundle`](https://github.com/parler-haut-interagir-librement/circuit-breaker-bundle) | Des circuits déclarés en configuration, leurs états partagés par les workers, et des clients HTTP derrière leurs circuits. |
| [`phil/bounce-parser`](https://github.com/parler-haut-interagir-librement/bounce-parser) | Sachez ce que dit chaque message de votre boîte de rebonds, et quoi faire de l'adresse qu'il nomme. |
| [`phil/api-contract`](https://github.com/parler-haut-interagir-librement/api-contract) | Les deux côtés du contrat d'une API HTTP : détails de problème, signaux de dépréciation, et un document OpenAPI vérifié selon des niveaux de règles. |
| [`phil/api-contract-bundle`](https://github.com/parler-haut-interagir-librement/api-contract-bundle) | Détails de problème, contrôleurs idempotents et dépréciés, et le document OpenAPI vérifié en intégration continue, pour Symfony. |
| [`phil/ai-bundle`](https://github.com/parler-haut-interagir-librement/ai-bundle) | Des tâches de modèles de langage sur Symfony AI, avec quotas, budgets et clés propres aux gens, et un journal des coûts qui n'enregistre jamais une invite ni une réponse. |
| [`phil/automations`](https://github.com/parler-haut-interagir-librement/automations) | Un constructeur d'automatisations sans code pour les événements d'un site : définitions JSON, délais, filtres et branches, reprises et compensation, par Messenger. |
| [`phil/lti`](https://github.com/parler-haut-interagir-librement/lti) | LTI 1.3 des deux côtés, outil et plateforme : le lancement OpenID Connect, Deep Linking 2.0, des rôles et des revendications typés, et les services de notes et de listes de LTI Advantage. |
| [`phil/lti-bundle`](https://github.com/parler-haut-interagir-librement/lti-bundle) | LTI 1.3 dans Symfony : les routes du lancement, de Deep Linking et du jeu de clés, un authentificateur des lancements vérifiés, des magasins sur Doctrine DBAL et des notes envoyées par Messenger. |
| [`phil/elearning-packages`](https://github.com/parler-haut-interagir-librement/elearning-packages) | Les paquets SCORM 1.2, SCORM 2004 et cmi5 lus de façon sûre, sans plateforme d'apprentissage : l'archive vérifiée avant toute lecture, le manifeste lu en valeurs. |
| [`phil/courses-bundle`](https://github.com/parler-haut-interagir-librement/courses-bundle) | Des cours en ligne dans Symfony : modules et activités, inscriptions, progression et achèvement, ouverture par date, import des paquets SCORM et cmi5, lancements cmi5 vérifiés, un magasin xAPI local ou distant. |
| [`phil/virtual-classroom`](https://github.com/parler-haut-interagir-librement/virtual-classroom) | Des classes en ligne en direct derrière un seul port : salles BigBlueButton et Jitsi, liens d'accès signés par rôle, assiduité avec heures d'arrivée et de départ, enregistrements. |
| [`phil/virtual-classroom-bundle`](https://github.com/parler-haut-interagir-librement/virtual-classroom-bundle) | Des classes en direct dans Symfony sur BigBlueButton ou Jitsi : liens d'accès par rôle, assiduité et enregistrements par des webhooks signés, assiduité comptée comme achèvement de cours. |
| [`phil/mime-reader`](https://github.com/parler-haut-interagir-librement/mime-reader) | Des courriels bruts lus comme des entrées hostiles : champs d'en-tête, parties MIME imbriquées et champs des rapports sous des limites, corps décodés à la demande en UTF-8. |
| [`phil/calendar-import`](https://github.com/parler-haut-interagir-librement/calendar-import) | Gardez un calendrier en phase avec des flux iCalendar externes : lus par URL selon un horaire, changements détectés, dernier bon état gardé quand un flux échoue, modifications des rédacteurs conservées. |

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
| [`phil/data-table-export`](https://github.com/parler-haut-interagir-librement/data-table-export) | Des tableaux exportés en CSV gardé contre l'injection de formules, ou en XLSX écrit par blocs, les mêmes octets pour la même feuille. |
| [`phil/import-wizard`](https://github.com/parler-haut-interagir-librement/import-wizard) | Importez des fichiers CSV sans surprise : un essai à blanc montre chaque ligne comme création, mise à jour ou refus, les correspondances se rejouent, et les lignes rejetées reviennent pour correction. |
| [`phil/incident-mode`](https://github.com/parler-haut-interagir-librement/incident-mode) | Un mode incident : des préréglages qui suspendent inscriptions, invitations ou messages pour une durée obligatoire, levés automatiquement. |
| [`phil/incident-mode-bundle`](https://github.com/parler-haut-interagir-librement/incident-mode-bundle) | Le mode incident dans Symfony : inscriptions, invitations ou formulaires suspendus pendant une attaque, un avis public honnête, une levée programmée, un journal et une page d'administration. |
| [`phil/status-page-bundle`](https://github.com/parler-haut-interagir-librement/status-page-bundle) | Une page d'état des services en HTML, JSON et Atom sans script, et un instantané statique poussé hors de l'infrastructure surveillée. |
| [`phil/error-tracking`](https://github.com/parler-haut-interagir-librement/error-tracking) | Le suivi des erreurs gardé chez soi : événements groupés par empreinte stable, purgés des données personnelles et des secrets avant toute écriture. |
| [`phil/error-tracking-bundle`](https://github.com/parler-haut-interagir-librement/error-tracking-bundle) | phil/error-tracking dans Symfony : requêtes, workers, commandes et navigateurs captés, groupes gardés avec une purge, pages par rôle et alertes de clavardage pour les nouveaux groupes et les régressions. |
| [`phil/data-table-bundle`](https://github.com/parler-haut-interagir-librement/data-table-bundle) | Des tableaux de données en Live Component : tri, filtres, pagination, actions par ligne et en lot, édition sur place, et le même état dans l'adresse sans JavaScript. |
| [`phil/sites-console-bundle`](https://github.com/parler-haut-interagir-librement/sites-console-bundle) | Exploitez de nombreux sites Symfony depuis une seule console : versions et santé tirées par requêtes signées, alertes, réglages poussés une fois, mises à jour par lots qui s'arrêtent à l'échec. |

Tous les paquets sont en `0.x` : leur API peut encore changer d'une version mineure à l'autre, comme le consigne leur
CHANGELOG. D'autres paquets rejoignent la vitrine par petits groupes quotidiens.

## Installer un paquet

Demandez le paquet voulu ; un bundle amène sa bibliothèque avec lui :

```sh
composer require phil/money-bundle
```

Un profil installe une famille de paquets en un seul require : `phil/profile-secure-input` pour ce que les usagers
envoient, `phil/profile-editorial` pour le contenu éditorial.

Le README et le dossier `doc/` de chaque paquet disent ce qu'il exige, comment le configurer et ce qu'il laisse à
l'application.

## Organisation

- `packages/<nom>/` : un paquet par dossier, installable seul, avec son `composer.json`, son README en anglais et en
  français, son CHANGELOG, sa licence, sa documentation dans `doc/` (miroir français dans `doc/fr/`) et ses tests.
- La racine les relie par un dépôt Composer `path` et porte l'outillage de qualité commun, qui vient de
  [`phil/dev-tools`](https://github.com/parler-haut-interagir-librement/dev-tools).

## Contribuer

Les tickets et les demandes de fusion vont dans **ce dépôt**, jamais dans les dépôts copiés, que chaque copie écrase.
Lisez le [guide de contribution](CONTRIBUTING.fr.md) et le [code de conduite](CODE_OF_CONDUCT.fr.md). Signalez une
vulnérabilité en privé, comme l'explique la [politique de sécurité](SECURITY.fr.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE). Chaque paquet porte son propre fichier de licence.
