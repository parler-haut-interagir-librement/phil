<!-- phil-dev source-sha256=2260389a8adc2bdacec5fece7df45aeb7d7cd41a36b37e154a4d64471f11f32a -->
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
| [`phil/epub`](https://github.com/parler-haut-interagir-librement/epub) | Des livres EPUB 3 à partir de HTML purifié ou de documents UJO : navigation, métadonnées d'accessibilité, polices, superpositions média et mise en page fixe. |
| [`phil/epub-bundle`](https://github.com/parler-haut-interagir-librement/epub-bundle) | Des livres bâtis par des sources déclarées par attribut, écrits tout de suite ou par un message Messenger, avec leurs polices et leurs images. |
| [`phil/latex-mathml`](https://github.com/parler-haut-interagir-librement/latex-mathml) | Des formules LaTeX converties en MathML Core, toute autre commande refusée avec sa position, longueur, profondeur et expansion bornées. |
| [`phil/svg-blocks`](https://github.com/parler-haut-interagir-librement/svg-blocks) | Les blocs math, mermaid, chart et code QR des documents UJO dessinés une fois à l'enregistrement, en SVG ou MathML signés. |
| [`phil/number-words`](https://github.com/parler-haut-interagir-librement/number-words) | Nombres en toutes lettres sur ICU : cardinaux, ordinaux, genre et montants d'argent, corrigés pour six langues. |
| [`phil/citations`](https://github.com/parler-haut-interagir-librement/citations) | Références gardées en CSL-JSON, import BibTeX et RIS, et styles auteur-date, notes et numérique en anglais et en français. |

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
| [`phil/almanac`](https://github.com/parler-haut-interagir-librement/almanac) | Les dates qui se calculent : Pâques, équinoxes, phases de la Lune, éclipses, lever et coucher du soleil, et les règles des fêtes. |
| [`phil/almanac-bundle`](https://github.com/parler-haut-interagir-librement/almanac-bundle) | Les dates calculées dans Doctrine, les formulaires et Twig, avec les lieux dont elles suivent le soleil et le ciel. |
| [`phil/calendar-sync`](https://github.com/parler-haut-interagir-librement/calendar-sync) | Les plages occupées lues dans les flux iCalendar, CalDAV, Google Agenda et Microsoft Graph, et les événements d'un site écrits chez eux. |

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
| [`phil/contact`](https://github.com/parler-haut-interagir-librement/contact) | Des fiches de contact : noms ordonnés selon leur culture, courriels, lignes téléphoniques et identifiants, écrits en vCard, JSContact et JSON-LD. |
| [`phil/contact-bundle`](https://github.com/parler-haut-interagir-librement/contact-bundle) | Un formulaire de fiche de contact, une colonne JSON, des téléchargements vCard et JSContact, et les noms de personnes dans Twig, pour Symfony. |

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
| [`phil/checkin`](https://github.com/parler-haut-interagir-librement/checkin) | L'enregistrement à l'entrée d'un événement ou d'un lieu : un code lu à une porte, décidé aussitôt, enregistré une fois, en ligne ou hors ligne. |
| [`phil/listings`](https://github.com/parler-haut-interagir-librement/listings) | Le noyau des annonces : une petite annonce, une offre d'emploi, une recherche de partenaires, rédigées, modérées, reçoivent des réponses et expirent selon les mêmes règles. |
| [`phil/open-badges`](https://github.com/parler-haut-interagir-librement/open-badges) | Des Open Badges 3.0 que tout le monde peut vérifier : émis, signés, intégrés à l'image, révoqués, appuyés et réunis dans un relevé d'apprentissage. |
| [`phil/directory-bundle`](https://github.com/parler-haut-interagir-librement/directory-bundle) | Un répertoire de fiches avec leur historique, leurs éditeurs et leurs liens, chacune trouvée comme son auteur le décide. |
| [`phil/checkin-bundle`](https://github.com/parler-haut-interagir-librement/checkin-bundle) | L'accueil dans Symfony : points de lecture, de manifeste et de synchronisation, et un poste qui continue hors ligne. |
| [`phil/comments-bundle`](https://github.com/parler-haut-interagir-librement/comments-bundle) | Des commentaires sur une page entière ou sur un passage d'un texte, avec des ancres qui suivent les modifications et des remplacements proposés. |
| [`phil/community-notes`](https://github.com/parler-haut-interagir-librement/community-notes) | Des notes communautaires écrites par des membres qualifiés et publiées par une règle d'accord croisé explicable. |
| [`phil/poll-bundle`](https://github.com/parler-haut-interagir-librement/poll-bundle) | Scrutins et bulletins dans Symfony : bulletins anonymes modifiés par reçu, et vote unique transférable par la méthode de Meek avec ses tours. |
| [`phil/stockroom`](https://github.com/parler-haut-interagir-librement/stockroom) | Un magasin d'accessoires : contenants imbriqués, objets comptés ou suivis un à un, journal de chaque mouvement, listes de chargement vérifiées au retour. |

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

### Contenu et recherche

| Paquet | Description |
|---|---|
| [`phil/content-bundle`](https://github.com/parler-haut-interagir-librement/content-bundle) | Du contenu par langue, étape et version : brouillons publiés par un workflow, corps publiés en UJO, routes qui gardent les anciens slugs, et une administration. |
| [`phil/locale-dimensions`](https://github.com/parler-haut-interagir-librement/locale-dimensions) | Les langues, les versions et les chaînes de repli entre langues, en objets-valeurs. |
| [`phil/publishing`](https://github.com/parler-haut-interagir-librement/publishing) | Les événements qu'envoie une page quand elle est mise en ligne ou retirée, pour ceux qui la suivent. |
| [`phil/scheduled-transitions-bundle`](https://github.com/parler-haut-interagir-librement/scheduled-transitions-bundle) | Publier ou dépublier à une date : des transitions de workflow planifiées et lancées par le Scheduler de Symfony. |
| [`phil/doctrine-behaviours`](https://github.com/parler-haut-interagir-librement/doctrine-behaviours) | Des comportements Doctrine : horodatage, auteurs, corbeille, arbres, slugs, positions et traductions. |
| [`phil/search-bundle`](https://github.com/parler-haut-interagir-librement/search-bundle) | La recherche avec facettes sur la base de données, Meilisearch ou Loupe, des synonymes par langue, et des filtres qui marchent sans JavaScript. |
| [`phil/search-loupe`](https://github.com/parler-haut-interagir-librement/search-loupe) | L'adaptateur Loupe de `phil/search-bundle` : des index de recherche dans des fichiers SQLite, sans serveur à faire tourner. |
| [`phil/search-synonyms`](https://github.com/parler-haut-interagir-librement/search-synonyms) | Des règles de synonymes par langue, vérifiées pour leurs chevauchements, lues et écrites aux formats Solr et JSON. |
| [`phil/translation-memory`](https://github.com/parler-haut-interagir-librement/translation-memory) | La traduction par segments : une mémoire de traduction, un glossaire, des contrôles de qualité, et les échanges XLIFF, TMX et TBX. |
| [`phil/translation-bundle`](https://github.com/parler-haut-interagir-librement/translation-bundle) | La traduction par segments dans Symfony : un écran de traduction, une mémoire et des glossaires dans Doctrine, des révisions et les échanges XLIFF. |
| [`phil/media-bundle`](https://github.com/parler-haut-interagir-librement/media-bundle) | Une médiathèque pour Symfony : des images autour de leur point focal en WebP ou AVIF, des SVG purifiés, des composants adaptatifs et un explorateur de fichiers. |

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
| [`phil/email-address`](https://github.com/parler-haut-interagir-librement/email-address) | Des adresses de courriel qu'un serveur peut livrer, avec des suggestions pour les fautes comme gmial.com. |
| [`phil/rate-limit`](https://github.com/parler-haut-interagir-librement/rate-limit) | Une garde de construction qui refuse, en production, une limite de débit comptée à part sur chaque serveur. |
| [`phil/rate-limit-bundle`](https://github.com/parler-haut-interagir-librement/rate-limit-bundle) | Des limites de débit déclarées sur les contrôleurs, les routes et les formulaires, avec des réponses 429 et les en-têtes RateLimit. |
| [`phil/safe-http`](https://github.com/parler-haut-interagir-librement/safe-http) | Lire les adresses que donnent les membres ou d'autres sites sans atteindre votre propre réseau : redirections vérifiées une à une. |
| [`phil/idempotency`](https://github.com/parler-haut-interagir-librement/idempotency) | Exécuter une opération une fois par clé d'idempotence, rejouer sa réponse, et traiter une fois chaque message de Messenger. |
| [`phil/browser-security-bundle`](https://github.com/parler-haut-interagir-librement/browser-security-bundle) | La Content Security Policy avec ses nonces, HSTS, des cookies signés et les autres protections qu'impose un navigateur, page par page. |
| [`phil/legal-notices`](https://github.com/parler-haut-interagir-librement/legal-notices) | Les mentions légales et la politique de confidentialité en documents versionnés, remplis à partir de l'identité de l'éditeur. |
| [`phil/resource-permissions`](https://github.com/parler-haut-interagir-librement/resource-permissions) | Permissions par ressource sur un seul modèle sujet-relation-objet : relations déclarées, droits hérités dans un arbre, vérifications et listes bornées. |
| [`phil/access-bundle`](https://github.com/parler-haut-interagir-librement/access-bundle) | Visibilité par élément, niveaux de partage qui expirent seuls, groupes d'accès et « voir comme ce membre » journalisé, décidés par des voteurs Symfony. |
| [`phil/access-codes`](https://github.com/parler-haut-interagir-librement/access-codes) | Codes à usage unique par lots nommés, remis une seule fois et gardés en empreintes à clé, rachetés de façon atomique, essais bornés par un limiteur. |
| [`phil/access-links`](https://github.com/parler-haut-interagir-librement/access-links) | Invitations par lien personnel : jeton gardé en empreinte à clé, réponse modifiable jusqu'à la clôture, rappels aux seuls non-répondants. |

### PHIL UX

| Paquet | Description |
|---|---|
| [`phil/ux-css`](https://github.com/parler-haut-interagir-librement/ux-css) | phil-ux, un cadre CSS presque sans classes sur des jetons `--phil-*`, clair et sombre, aux thèmes vérifiés pour le contraste. |
| [`phil/ux-kit`](https://github.com/parler-haut-interagir-librement/ux-kit) | Le kit PHIL UX du Symfony UX Toolkit : les recettes du kit Shadcn, habillées par phil-ux, copiées par `ux:install`. |
| [`phil/ux-bundle`](https://github.com/parler-haut-interagir-librement/ux-bundle) | Le thème de formulaire PHIL UX sur chaque formulaire d'une application Symfony, et la feuille de `phil/ux-css` dans AssetMapper. |
| [`phil/ux-easyadmin-bundle`](https://github.com/parler-haut-interagir-librement/ux-easyadmin-bundle) | L'administration EasyAdmin aux couleurs, coins et polices des jetons de phil-ux, en clair et en sombre. |

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
| [`phil/automations`](https://github.com/parler-haut-interagir-librement/automations) | Un constructeur d'automatisations sans code pour les événements d'un site : définitions JSON, délais, filtres et branches, reprises et compensation, par Messenger. |

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
| [`phil/incident-mode`](https://github.com/parler-haut-interagir-librement/incident-mode) | Un mode incident : des préréglages qui suspendent inscriptions, invitations ou messages pour une durée obligatoire, levés automatiquement. |
| [`phil/status-page-bundle`](https://github.com/parler-haut-interagir-librement/status-page-bundle) | Une page d'état des services en HTML, JSON et Atom sans script, et un instantané statique poussé hors de l'infrastructure surveillée. |
| [`phil/error-tracking`](https://github.com/parler-haut-interagir-librement/error-tracking) | Le suivi des erreurs gardé chez soi : événements groupés par empreinte stable, purgés des données personnelles et des secrets avant toute écriture. |

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
