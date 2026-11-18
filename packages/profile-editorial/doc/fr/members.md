<!-- phil-dev source-sha256=17cbe04a39b74800e5f994ba35847a3c80bd38cbac8e35621f2f23341a9b968f -->
# Membres

Chaque membre est un paquet indépendant, publié sur Packagist et versionné séparément. Le profil l'exige à sa dernière
version mineure publiée.

## `phil/markup-bundle` — du texte en HTML sûr

- **Contrainte :** `^0.1`. **Apporte :** `phil/markup` 0.2, `phil/ujo`, `phil/svg-sanitizer`, `phil/css-sanitizer`.
- **Rôle :** un texte écrit en Markdown, en BBCode, en syntaxes wiki, en UJO (Universal JSON Output) ou en HTML est
  affiché en HTML purifié au niveau que choisit l'application : basique, forum ou tout.
- **Ajoute :** la configuration `phil_markup`, les filtres Twig `markup_html`, `markup_purify` et `markup_ujo`, le
  champ de formulaire `MarkupFormatType`, la conversion d'une source en UJO.
- **Pourquoi dans ce profil :** tous les autres membres travaillent sur du texte écrit par des auteurs, et ce texte
  doit s'afficher sans laisser passer de script.
- **Exige :** `ext-dom`, `ext-libxml`, `ext-ctype`, `ext-filter` et `ext-mbstring` ; Twig pour les filtres.

## `phil/citations` — sources et bibliographies

- **Contrainte :** `^0.1`.
- **Rôle :** des références conservées en CSL-JSON, importées depuis BibTeX et RIS, citées et listées selon les styles
  auteur-date, notes ou numérique, en anglais ou en français.
- **Ajoute :** `CitationProcessor`, `Cite`, `ReferenceList` et les importateurs ; une bibliothèque, sans bundle.
- **Pourquoi dans ce profil :** un contenu éditorial qui avance des affirmations nomme ses sources, et une
  bibliographie fait partie du texte.
- **Exige :** `ext-intl`, `ext-ctype` et `ext-mbstring`.

## `phil/footnotes-sidenotes` — des notes à côté du texte

- **Contrainte :** `^0.1`. **Apporte :** `phil/ujo`.
- **Rôle :** les notes de bas de page et les notes marginales d'un document UJO, numérotées et vérifiées, affichées en
  notes de fin accessibles, en notes latérales sur les grands écrans, ou au bas des pages imprimées.
- **Ajoute :** `NoteResolver`, ses options et son HTML ; une bibliothèque, sans bundle.
- **Pourquoi dans ce profil :** les notes portent les remarques incidentes et les citations de l'écriture longue.
- **Exige :** `ext-mbstring`. `phil/paged-media` et `phil/typography` sont utilisés quand ils sont installés.

## `phil/content-warnings-bundle` — des avertissements que les lecteurs contrôlent

- **Contrainte :** `^0.1`. **Apporte :** `phil/content-warnings`.
- **Rôle :** un catalogue de thèmes d'avertissement tiré de la configuration, en six langues ; des avertissements sur
  n'importe quelle entité ; un contenu replié ou masqué selon le choix de chaque lecteur.
- **Ajoute :** le type de colonne `phil_content_warnings`, les champs `ContentWarningsType` et
  `WarningPreferencesType`, les fonctions Twig et le gabarit `@PhilContentWarnings/fold.html.twig`, le contrôleur
  Stimulus `reveal`.
- **Pourquoi dans ce profil :** une publication honnête dit ce qu'une pièce contient avant que le lecteur la rencontre.
- **Exige :** `ext-intl` ; Twig, les formulaires et Doctrine DBAL pour les parties qui les utilisent.

## `phil/structured-data-bundle` — ce que lisent les moteurs de recherche

- **Contrainte :** `^0.1`. **Apporte :** `phil/structured-data`, `phil/indexing-consent`, `phil/url`.
- **Rôle :** un graphe schema.org par page, alimenté par la configuration, les paquets et les gabarits, fusionné par
  `@id`, vérifié et imprimé de façon sûre en JSON-LD ; une page retirée des moteurs de recherche n'en imprime aucun.
- **Ajoute :** la configuration `phil_structured_data`, les fonctions Twig `structured_data()`, `structured_data_add()`
  et `structured_data_about()`, la commande `phil:structured-data:check`.
- **Pourquoi dans ce profil :** on trouve les articles, les livres et les événements grâce aux données qui les
  décrivent.
- **Exige :** `ext-dom`, `ext-json` et `ext-libxml` ; Twig pour les fonctions.

## Paquets voisins de ce profil

Ces paquets ne sont pas des membres : ils répondent à d'autres besoins, et une application les ajoute quand elle a ces
besoins.

| Paquet | Pourquoi ce n'est pas un membre |
|---|---|
| `phil/typography`, `phil/typography-bundle` | Suggérés par `phil/markup-bundle` et `phil/footnotes-sidenotes` pour les règles typographiques d'une langue ; une application qui fait de la composition typographique les ajoute. |
| `phil/paged-media` | La sortie imprimée et PDF ; utilisé par `phil/footnotes-sidenotes` quand il est installé. |
| `phil/social-cards-bundle` | Suggéré par `phil/structured-data-bundle` pour les aperçus de partage ; un choix qui concerne les réseaux sociaux. |
| `phil/ujo-bundle`, `phil/block-editor-bundle` | La modification de documents UJO dans le navigateur ; le profil porte sur leur publication. |
