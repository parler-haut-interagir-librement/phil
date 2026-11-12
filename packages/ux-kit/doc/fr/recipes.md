<!-- phil-dev source-sha256=84e0baf20327e6cea1718a9a4a9c481301cbebdab945db579badffab2f770751 -->
# Recettes

Chaque recette a son propre README, avec des exemples, dans son dossier ; cette page rassemble leurs props. Chaque
composant prend aussi d'autres attributs, écrits sur son élément racine, une `class` s'ajoutant à la sienne.

## Logo

`<twig:Logo>` : un `span`, ou un `a` avec `href`, de classe `isLogo`, qui contient une `Picture` de la recette
`image`, dont il dépend.

| Prop | Par défaut | |
|---|---|---|
| `src` | requis | L'image là où ni l'AVIF ni le WebP n'est lu, PNG ou SVG |
| `alt` | requis | Le nom qu'écrit le logo ; vide seulement quand le nom est écrit à côté |
| `webp` | `null` | Une source WebP, une URL ou un tableau de densités, offerte après l'AVIF |
| `avif` | `null` | Une source AVIF, une URL ou un tableau de densités, offerte d'abord |
| `srcset` | `null` | Les densités du repli, telles que HTML les écrit ou en tableau |
| `dark` | `null` | Le logo d'un système sombre, une URL ou un tableau de densités, offert là où `prefers-color-scheme` est sombre |
| `still` | `null` | Le logo fixe d'un logo animé, une URL ou un tableau de densités, offert là où le mouvement est réduit |
| `forced` | `null` | Le logo dessiné pour les couleurs forcées, une URL ou un tableau de densités, offert d'abord là où `forced-colors` est actif |
| `priority` | `false` | L'image principale de la page, préchargée par WebLink et demandée en premier, comme pour `Picture` |
| `layout`, `aspect`, `fit`, `position` | `null` | La disposition, le rapport, l'ajustement et le recadrage de l'image, comme pour `Image` |
| `theme` | `null` | Remplace `{theme}` dans chaque URL |
| `locale` | `null` | Remplace `{locale}` dans chaque URL |
| `href` | `null` | Un lien autour du logo |
| `width`, `height` | `null` | La taille de l'image, pour réserver sa place |
| `loading` | `eager` | `eager` au-dessus de la ligne de flottaison, `lazy` en dessous |

L'`img` porte `isImage`, sous la hauteur d'`isLogo` ; une URL refusée est laissée de côté comme dans `Picture`.

## AnimatedHeadline

`<twig:AnimatedHeadline>` : un titre de classe `isHeadline`, contrôleur `animated-headline`.

| Prop | Par défaut | |
|---|---|---|
| `words` | requis | Le mot, ou la liste des mots à jouer |
| `text` | `null` | Un texte fixe avant les mots |
| `suffix` | `null` | Un texte fixe après les mots |
| `level` | `1` | Le niveau du titre, tenu entre 1 et 6 |
| `label` | `null` | Ce que lisent les lecteurs d'écran à la place du texte, des mots et du suffixe |
| `separator` | `', '` | Ce qui joint les mots dans ce que lisent les lecteurs d'écran |
| `pause` | `2500` | Les millisecondes pendant lesquelles un mot se repose |
| `duration` | `600` | Les millisecondes que prend le découpage pour se fermer, autant pour s'ouvrir |
| `loop` | `false` | Si plusieurs mots tournent en boucle, avec un bouton de pause et de lecture, au lieu d'être joués une fois |
| `pauseLabel` | `Pause animation` | Le texte du bouton pendant que les mots tournent |
| `playLabel` | `Play animation` | Le texte du bouton une fois en pause |

Avec `loop` et plus d'un mot, le titre contient, après les mots, un `<button type="button">` de classe
`isRevealToggle`, `hidden` jusqu'à ce que le contrôleur l'affiche, et n'est nommé par `aria-labelledby` que par sa
copie visuellement cachée. Sans `loop`, ou avec un seul mot, il n'y a pas de bouton et `loop-value` vaut `false`.

Le contrôleur `animated-headline` :

| Nature | Nom | |
|---|---|---|
| Valeur | `pause`, `duration`, `loop`, `pauseLabel`, `playLabel` | Tirées des props |
| Cible | `reveal` | Le découpage autour des mots empilés |
| Cible | `word` | Chaque mot, dans l'ordre |
| Cible | `toggle` | Le bouton de pause et de lecture |
| Action | `toggle` | Met la boucle en pause, ou la relance |

Il ajoute `isRevealing` au découpage, pose `--phil-reveal-size` pour le fermer jusqu'à son curseur puis l'ouvrir sur
le mot suivant, déplace `isCurrent`, et ajoute `isPulsing` pendant qu'un mot se repose. Un seul mot est ouvert une fois,
puis le contrôleur lâche prise : le découpage revient à sa largeur naturelle. Plusieurs mots sont joués une fois,
jusqu'au mot marqué `isCurrent` par le serveur ; `timing()` raccourcit les repos et les mouvements pour que le déroulé,
entrée comprise, se termine dans `BUDGET` (4800 ms), sous les cinq secondes des WCAG 2.2.2. Avec `loop`, les durées
sont gardées, les mots tournent, et le bouton est affiché, son texte passant d'un libellé à l'autre.

La boucle se retient, le mot courant tout ouvert, pendant que le titre est survolé (`mouseenter`, `mouseleave`), garde
le focus (`focusin`, `focusout` qui le quitte), est mis en pause par le bouton, ou que le document est caché
(`visibilitychange`) ; elle reprend dès que plus rien ne la retient, et appuyer sur la lecture lâche aussitôt le
pointeur et le focus. Chaque mouvement se termine sur le `transitionend` du découpage, ou après `duration` quand
l'événement ne vient jamais. Il ne fait rien sous `prefers-reduced-motion`, s'arrête quand le lecteur active la
préférence, démarre quand elle est désactivée, mesure de nouveau le mot au redimensionnement, et retire ses écouteurs
et sa minuterie à la déconnexion.

## Illustration

`<twig:Illustration>` : une `figure` qui contient une `picture` quand la candidate choisie a une source WebP, une `img`
sinon.

| Prop | Par défaut | |
|---|---|---|
| `candidates` | requis | Une URL, ou une liste d'URL et de tableaux de `src`, `webp`, `alt`, `width`, `height` |
| `alt` | requis | Le texte de remplacement d'une candidate qui n'en a pas ; vide pour une image décorative |
| `seed` | `null` | La candidate est le germe modulo leur nombre ; `null` en tire une au hasard |
| `caption` | `null` | Un `figcaption` |
| `loading` | `lazy` | `lazy` sous la ligne de flottaison, `eager` au-dessus |
| `sizes` | `null` | Le `sizes` d'une source adaptative |

Aucune candidate ne produit rien.

## Image

La recette `image` contient deux composants, sous les noms de props de Symfony UX Image, et le contrôleur Stimulus
`image-preview`, qui peint un aperçu flou seulement quand on en donne un ; aucun style en ligne. Elle appelle
`image_origin()` et `image_check()` du bundle de `phil/ux-kit` et `preload()` de Symfony WebLink, et demande les deux
paquets et le StimulusBundle.

`<twig:Image>` : une `img` de classe `isImage`, dans une `figure` avec sa `caption`, son crédit, sa licence, sa création
et sa description.

| Prop | Par défaut | |
|---|---|---|
| `src` | requis | L'URL de l'image |
| `alt` | requis | Ce que l'image montre ; vide pour une image décorative |
| `width`, `height` | requis | La taille de l'image : sa place est réservée à son rapport avant qu'elle se charge |
| `srcset` | `null` | Les candidates telles que HTML les écrit, ou un tableau de densités (`2x`), ou de largeurs avec `sizes` (`480w`), vers des URL |
| `sizes` | `null` | Écrit avec un `srcset`, après `auto,` tant que l'image se charge en différé ; calculé d'après la disposition quand il est nul |
| `layout` | `null` | `fixed`, à sa largeur (tableaux lus comme densités) ; `constrained`, jamais plus large que sa largeur, `sizes` `(min-width: <width>px) <width>px, 100vw` ; `full-width`, sur toute la largeur de son conteneur, `sizes` `100vw` ; aucune : l'image rétrécit avec son conteneur, `sizes` tel que donné |
| `aspect` | `null` | Le rapport de sa boîte : `16/9`, `4/3`, `3/2`, `1/1`, `21/9`, `3/4`, `2/3`, `9/16`, ou `custom`, lu dans `--phil-image-ratio`, qu'une classe du site pose |
| `fit` | `null` | `cover`, par défaut avec un rapport, ou `contain` |
| `position` | `null` | Où elle est rognée : `center`, `top`, `bottom`, `left` ou `right` |
| `priority` | `false` | L'image principale au-dessus de la ligne de flottaison : `loading="eager"`, `fetchpriority="high"`, aucun `decoding`, préchargée |
| `preload` | `null` | Si l'image est préchargée par Symfony WebLink, dans un en-tête `Link` ; avec `priority` quand il est nul |
| `loading` | `null` | `lazy` ou `eager`, qui l'emporte sur `priority` ; `lazy` quand il est nul sans `priority` |
| `decoding` | `null` | `async`, `sync` ou `auto`, qui l'emporte sur `priority` ; `async` quand il est nul sans `priority` |
| `caption` | `null` | Une `figcaption`, échappée : l'image est alors dans une `figure`, décorative ou non |
| `captionPosition` | `'below'` | `below`, sous l'image, ou `overlay`, sur son pied, sur un voile assez sombre pour un texte clair, la `figure` de classe `hasOverlayCaption` |
| `credit` | `null` | Qui a fait l'image, tel que le site l'écrit, dans un `small` de classe `isCredit` sous la légende |
| `license`, `licenseHref` | `null` | Le nom de la licence, dans un `small` de classe `isLicense`, lié à son adresse avec `rel="license"` |
| `creation` | `null` | Un code du vocabulaire IPTC Digital Source Type, ou une énumération dont la valeur en est un, dit dans un `small` de classe `isCreation` |
| `creationLabel` | `null` | Les mots qui disent la création ; le nom que le vocabulaire donne au code quand il est nul |
| `deferred` | `false` | Une image loin sous la ligne de flottaison, demandée après les autres : `fetchpriority="low"` ; `priority` l'emporte |
| `placeholder` | `null` | La couleur de sa boîte pendant le chargement, une teinte discrète des jetons : `neutral`, `brand`, `red`, `green`, `amber`, `blue`, `violet` (`hasPlaceholderBlue`…), ou `custom`, lue dans `--phil-image-placeholder` (`hasPlaceholder`) |
| `preview` | `null` | Une toute petite version de l'image, en ligne en base 64 (PNG, JPEG, GIF, WebP ou AVIF), peinte floue derrière elle pendant son chargement par le contrôleur `image-preview` (`hasPreview`) ; toute autre valeur laissée de côté |
| `description` | `null` | Une description longue, échappée, dans un `details` de classe `isDescription` après l'image, dont le texte décrit l'image par `aria-describedby` |
| `descriptionLabel` | `'Description'` | Les mots du `summary` de la description |

`<twig:Picture>` : une `picture` dont les éléments `source`, dans l'ordre donné, précèdent une `Image` qui porte le
repli ; l'`Image` seule quand il ne reste aucune source ; dans une `figure` avec sa `caption`.

| Prop | Par défaut | |
|---|---|---|
| Les props d'`Image` | | Portées par l'`img` de repli ; `sizes` sert aussi les sources qui n'en ont pas |
| `sources` | `[]` | Chacune `{type, srcset, sizes, media, width, height}` : `type` un type `image/*` ou absent, `srcset` comme pour `Image`, `media` et la taille de son recadrage pour la direction artistique |
| `dark` | `null` | Offerte là où `prefers-color-scheme` est sombre : des candidates comme pour `srcset`, ou des sources `{type, srcset}` |
| `still` | `null` | Offerte là où `prefers-reduced-motion` vaut reduce, en premier : des candidates comme pour `srcset`, ou des sources `{type, srcset}` |
| `forced` | `null` | Offerte là où `forced-colors` est actif, avant toute autre source : des candidates comme pour `srcset`, ou des sources `{type, srcset}` |

Les sources sont écrites dans cet ordre : la variante des couleurs forcées, l'image fixe, la variante sombre, puis
celles qui ont une requête `media`, dans l'ordre de leur première requête, puis les autres ; dans chaque groupe,
l'AVIF, puis le WebP, puis les autres types, puis les sources sans type. Un `media` qui contient `<`, `>`, `{`, `}` ou
`;` laisse sa source de côté ; un `width` ou un `height` qui n'est pas un entier positif est laissé de côté. La
disposition, le rapport, l'ajustement, le recadrage, la couleur d'attente et l'aperçu sont des classes de phil-ux sur
l'`img` (`hasLayoutFixed`, `hasLayoutFullWidth`, `hasRatio16x9`…, `hasFitCover`, `hasPositionTop`…,
`hasPlaceholderBlue`…, `hasPreview`), jamais un style en ligne.

Les autres attributs vont à l'`img`, pas à une racine, et seulement `id`, `class` (ajoutée à `isImage`), `title`,
`crossorigin`, `referrerpolicy`, `itemprop`, `draggable`, `data-*` et `aria-*`, écrits avec `html_attr()` de
`twig/html-extra` ; une image décorative laisse aussi de côté `title` et `aria-*`, qui la nommeraient. Une valeur de
`loading` ou de `decoding` hors de celles listées revient à la valeur par défaut. Une URL `javascript:`, `vbscript:`
ou `data:` autre que `data:image/` est refusée, quelle que soit sa casse, après des espaces ou des caractères de
contrôle, ou coupée par des tabulations et des sauts de ligne : un `src` refusé ne produit rien, un `srcset` refusé
est laissé de côté en entier, comme une source qui contient une URL refusée, n'a aucune candidate ou a un autre type
qu'`image/*`.

Une adresse d'un autre hôte est refusée à moins que `phil_ux_kit.image.hosts` liste son hôte, lu par `phil/url` : un
chemin relatif, un chemin `data:image/` et l'origine même de la page sont à la page. Une image d'un hôte listé est
demandée avec `referrerpolicy="no-referrer"`, à moins que la page donne une autre politique. Avec `priority`, `Image`
précharge son `src` avec son `srcset` et ses `sizes` ; `Picture` précharge sa première source avec son `type`, et rien
quand une source a une requête `media`, les variantes comprises. Tant que le noyau est en débogage, ou avec
`phil_ux_kit.image.check`, `image_check()` journalise un avertissement pour un texte de remplacement qui est le nom du
fichier, un mot qui dit seulement que c'est une image ou qui commence par le dire, et pour une largeur ou une hauteur
absente.

`image_sources('images/logo')` lit les fichiers d'une image parmi les ressources d'AssetMapper, `logo.avif`,
`logo@2x.avif`, `logo.webp`, `logo.png`…, et donne le `src`, le `srcset`, les `sources`, le `width` et le `height`
d'une `Picture` ; ses options sont `densities`, `widths`, `formats` et `fallbacks`.

Les props gardent les noms et le sens de celles de Symfony UX Image, le chargement différé par défaut compris, pour
qu'une page passe de l'un à l'autre en changeant la balise ; la recette ne transforme aucune image.

## Gallery

`<twig:Gallery>` : un `div` de classe `isGallery` qui contient un `ul` nommé par `label`, chaque image une `Picture`
dans son `li`, ou une `Lightbox` ; rien sans image.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Le nom de la liste pour les lecteurs d'écran |
| `images` | `[]` | Chacune avec les props d'une `Picture`, `src`, `alt`, `width`, `height` requis, et `full` pour sa visionneuse |
| `aspect`, `fit` | `null` | Le rapport et l'ajustement de chaque image |
| `sizes` | `null` | La largeur d'une colonne, pour les candidates de largeurs |
| `lightbox` | `false` | Si chaque image s'ouvre dans une `Lightbox` |
| `id` | `null` | L'identifiant de la galerie, dont on tire ceux de ses visionneuses ; `gallery` quand il est nul |
| `openLabel`, `closeLabel` | `null`, `'Close'` | Les mots des boutons de chaque visionneuse |
| `deferred` | `false` | Une longue galerie : ses figures hors de l'écran sautées par `content-visibility: auto` (`isDeferred`), chaque `li` dimensionné par ses `data-width` et `data-height`, ses images demandées en dernier |
| `placeholder` | `null` | La couleur d'attente de chaque image, comme pour `Image` |
| `descriptionLabel` | `'Description'` | Les mots du résumé de la `description` de chaque image |
| `layout` | `null` | `carousel` : une rangée qui défile et s'aimante, que le clavier atteint (`hasLayoutCarousel`, `tabindex="0"`), ses marqueurs et ses boutons dessinés par `::scroll-marker` et `::scroll-button()` là où le navigateur les a ; une grille sinon |
| `previousLabel`, `nextLabel` | `'Previous'`, `'Next'` | Les noms des boutons d'un carrousel, portés par la liste dans `data-previous` et `data-next` ; chaque marqueur est nommé par le `data-marker` de son `li`, le texte de remplacement de son image ou son rang |
| `captionPosition` | `'below'` | Où se tient la légende de chaque image, comme pour `Image` ; une visionneuse garde la sienne sous sa vignette |

## Lightbox

`<twig:Lightbox>` : un `div` de classe `isLightbox`, contrôleur `lightbox`, qui contient la vignette dans un bouton de
classe `isLightboxTrigger` qui ouvre, par `command="show-modal"` et `commandfor`, un `dialog` de classe
`isLightboxDialog` avec `closedby="any"`, son image entière et son bouton de fermeture `isLightboxClose`.

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | Unique dans la page ; celui du dialogue est `lightbox-<id>` |
| `src`, `alt`, `width`, `height` | requis | La vignette ; `alt` nomme aussi le dialogue, la légende quand il est vide |
| `srcset`, `sizes`, `sources`, `aspect`, `fit`, `position` | | Ceux de la vignette, comme pour `Picture` |
| `full` | `null` | L'image du dialogue, `{src, width, height, srcset, sizes, sources}`, montrée entière, de `sizes` `100vw` à moins d'en avoir ; la vignette quand il est nul |
| `caption`, `credit`, `license`, `licenseHref`, `creation`, `creationLabel` | `null` | Sous la vignette et sous l'image entière |
| `openLabel` | `null` | Des mots lus après le texte de remplacement dans le bouton |
| `closeLabel` | `'Close'` | Ce que les lecteurs d'écran lisent pour le bouton de fermeture |
| `deferred`, `placeholder`, `preview` | `false`, `null`, `null` | Ceux de la vignette, comme pour `Image` |
| `description`, `descriptionLabel` | `null`, `'Description'` | Une description longue après la vignette, hors de son bouton, qu'elle décrit |

## Hotspots

`<twig:Hotspots>` : un `div` de classe `isHotspots` qui contient une `Picture` de la recette `image` et, par-dessus, un
`ul` de classe `isHotspotsList` nommé par `label`, chaque point un `li` de classe `isHotspot` qui contient son lien ;
dans une `figure` avec sa légende, son crédit et sa licence. Une image que la recette `image` refuse ne dessine rien.

| Prop | Par défaut | |
|---|---|---|
| `src`, `alt`, `width`, `height` | requis | L'image, comme pour `Image` |
| `label` | requis | Le nom de la liste des points pour les lecteurs d'écran |
| `spots` | `[]` | Chacun `{x, y, label, href}` : sa place en pourcentages de la largeur depuis la gauche et de la hauteur depuis le haut, ramenée à 100 au plus, ses mots et son adresse |
| `sources`, `srcset`, `sizes`, `priority`, `placeholder` | | Ceux de l'image, comme pour `Picture` |
| `caption`, `credit`, `license`, `licenseHref` | `null` | Sous l'image, dans une `figure` |

Chaque point porte les classes `hasHotspotX0` à `hasHotspotX100` et `hasHotspotY0` à `hasHotspotY100`, sa place aux
cinq pour cent près, et `data-x` et `data-y`, sa place exacte, que phil-ux lit par un `attr()` typé là où le navigateur
le fait ; jamais de `style`. Un point sans mots, sans adresse, avec une adresse `javascript:`, `vbscript:` ou `data:`,
ou dont la place n'est pas un nombre à partir de 0, est écarté ; sans point, pas de liste. `id` et `class` vont au `div`.

## Hero

`<twig:Hero>` : un `div` de classe `isHero` qui contient un `div` de classe `isHeroMedia`, sa `Picture` disposée en
`full-width` et qui couvre la boîte, puis un `div` de classe `isHeroContent`, son contenu par-dessus l'image.

| Prop | Par défaut | |
|---|---|---|
| `src`, `alt`, `width`, `height` | requis | L'image, comme pour `Image` ; `alt` vide quand elle ne fait que planter le décor |
| `sources`, `srcset`, `placeholder`, `preview`, `position` | | Ceux de l'image, comme pour `Picture` |
| `sizes` | `null` | `100vw` quand il est nul |
| `priority` | `true` | L'image principale de la page : chargée tout de suite, demandée d'abord, préchargée par WebLink |
| `size` | `'screen'` | `screen` (`hasHeroScreen`), `large` (`hasHeroLarge`) ou `auto` |
| `align` | `'end'` | Où se tient le contenu dans la hauteur : `start` (`hasHeroStart`), `center` (`hasHeroCenter`) ou `end` |

| Bloc | |
|---|---|
| `content` | Ce qui se tient par-dessus l'image, sur son voile |

Une image que la recette `image` refuse laisse le contenu seul. `id` et `class` vont à la racine.

## Video

`<twig:Video>` : une `video` de classe `isVideo` avec `controls`, `playsinline` et `preload="none"`, ses éléments
`source` et `track`, puis un lien vers la première source ; dans une `figure` avec sa transcription, sa légende, son
crédit, sa licence et sa création. Sans source restante, rien n'est dessiné.

| Prop | Par défaut | |
|---|---|---|
| `width`, `height` | requis | La taille de la vidéo, pour réserver sa place |
| `src` | `null` | Une source unique |
| `sources` | `[]` | Chacune `{src, type}`, après `src` ; une source `data:`, un type autre que `video/*` ou un hôte refusé l'écarte |
| `poster` | `null` | L'image montrée jusqu'à la lecture : une adresse que la recette `image` montrerait, ou une `data:image/` |
| `tracks` | `[]` | Chacune `{src, srclang, label, kind, default}`, un fichier WebVTT : `kind` `captions`, par défaut, `subtitles`, `descriptions` ou `chapters` |
| `preload` | `'none'` | `none` ou `metadata` ; toute autre valeur vaut `none` |
| `muted`, `loop` | `false` | Pour un court extrait muet |
| `label` | `null` | Le nom de la vidéo, `aria-label`, quand aucune légende ne la nomme |
| `fallbackLabel` | `'Download the video'` | Les mots du lien que montre un navigateur qui ne lit aucune vidéo |
| `caption`, `credit`, `license`, `licenseHref`, `creation`, `creationLabel` | `null` | Comme pour `Image` |
| `transcript` | `null` | Un dévoilement de classe `isDescription` après la vidéo, qui la décrit par `aria-describedby` |
| `transcriptLabel` | `'Transcript'` | Les mots de son résumé |

Aucune lecture automatique : un attribut `autoplay` est écarté. Ses adresses sont contrôlées comme celles d'une image,
par `image_origin()`. `id` et `class` vont à la `video`.

## Figure

`<twig:Figure>` : un `div` de classe `isFigureGroup` qui contient les images de son contenu, dans une `figure` avec
une légende, un crédit, une licence et une création pour toutes ; le `div` seul sans eux.

| Prop | Par défaut | |
|---|---|---|
| `caption`, `credit`, `license`, `licenseHref`, `creation`, `creationLabel` | `null` | Comme pour `Image`, écrits une fois sous les images |
| `columns` | `null` | `2`, `3` ou `4` colonnes sur tous les écrans (`hasTwoColumns`…) ; autant que le conteneur le permet quand il est nul |

| Bloc | |
|---|---|
| `content` | Les images, chacune une `Image` ou une `Picture` sans légende à elle |

`id` et `class` vont au `div`.

## ReactionButton

`<twig:ReactionButton>` : un `<button type="submit">` de classe `isReaction`, avec `aria-pressed` ; ou un `<span>` de
la même classe quand `interactive` est faux. L'émoji et le compteur visible sont cachés aux lecteurs d'écran, qui lisent
le libellé et `countLabel` ; la classe fait une cible de 2.75rem de large et de haut. Aucun compteur n'est écrit à zéro.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Le nom de la réaction |
| `emoji` | `null` | L'émoji Unicode |
| `image` | `null` | L'adresse de l'image, quand la réaction n'a pas d'émoji |
| `imageAlt` | `null` | Le texte de remplacement de l'image ; `label` quand il est nul |
| `count` | `null` | Le compteur ; aucun n'est affiché quand il est nul |
| `countLabel` | `null` | Ce que lisent les lecteurs d'écran à la place du compteur |
| `pressed` | `false` | Si cette réaction est celle du lecteur |
| `interactive` | `true` | Un bouton, ou du texte simple |

## ReactionPicker

`<twig:ReactionPicker>` : un `<details>` de classe `isReactionPicker`, contrôleur `phil--reactions-bundle--picker` de
`phil/reactions-bundle`, écrit dans le formulaire qui reçoit ses boutons. Le champ de recherche reste `hidden` jusqu'à
ce que le contrôleur l'affiche ; chaque bouton fonctionne sans lui. Des `attributes` d'une option, seuls `name`, `value`,
`title`, `data-*` et `aria-*` sont écrits : un gestionnaire d'événement ou un `formaction` donné là est laissé de côté.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Le nom du dévoilement |
| `groups` | requis | Les intertitres, `{label, options}`, chaque option `{label, emoji, image, imageAlt, attributes}`, les attributs écrits sur son bouton |
| `searchLabel` | `Search reactions by name` | Le nom du champ de recherche |
| `emptyLabel` | `No reaction has this name.` | Ce qui s'affiche quand la recherche ne trouve rien |
| `resultsLabel` | `Reactions shown: {count}` | Ce que les lecteurs d'écran entendent après une recherche |

## AutosaveStatus

`<twig:AutosaveStatus>` : un bloc de classe `isStack`, dans un formulaire dont la balise porte le contrôleur
`phil--forms-autosave--autosave` de `phil/forms-autosave`. Il contient un paragraphe de classe `isMuted`,
`role="status"` et `aria-live="polite"`, la cible `status` ; et l'avis, de classe `isCluster`, `role="group"` et
`hidden`, la cible `notice`, avec son paragraphe `message` et trois boutons, cibles et actions `useServer` (caché),
`discard` et `dismiss`. Le contrôleur écrit les messages et affiche les boutons dont chaque avis a besoin.

| Prop | Par défaut | |
|---|---|---|
| `discardLabel` | `Discard the draft` | Le bouton qui remet les valeurs affichées et oublie le brouillon |
| `dismissLabel` | `Keep it` | Le bouton qui cache l'avis et garde les valeurs dans le formulaire |
| `useServerLabel` | `Use the newer draft` | Le bouton qui met dans le formulaire le brouillon plus récent d'un autre appareil ou onglet |

## ComingSoon

`<twig:ComingSoon>` : un bloc de classe `isSplit isFullHeight`.

| Prop | Par défaut | |
|---|---|---|
| `logoSrc`, `logoAlt` | requis | L'image et le nom du logo |
| `eyebrow` | requis | Le libellé au-dessus du titre |
| `words` | requis | Le mot, ou les mots, du titre |
| `logoWebp`, `logoHref` | `null` | La source WebP et le lien du logo |
| `theme`, `locale` | `null` | Remplacent `{theme}` et `{locale}` dans les sources du logo |
| `headlineText` | `null` | Un texte fixe avant les mots du titre |
| `paragraphs` | `[]` | Des paragraphes sous le titre, échappés |
| `illustrations` | `[]` | Les candidates de l'illustration ; aucune laisse la colonne de côté |
| `illustrationAlt` | `''` | Le texte de remplacement de l'illustration |
| `seed` | `null` | Le germe de l'illustration |
| `landmarks` | `true` | `header` et `main`, ou `div` quand la mise en page les a |
| `logoWidth`, `logoHeight` | `null` | La taille du logo, pour réserver sa place |
| `logoPriority` | `false` | Le logo préchargé par WebLink et demandé en premier |
| `logoLayout`, `logoAspect`, `logoFit`, `logoPosition` | `null` | La disposition, le rapport, l'ajustement et le recadrage du logo, comme pour `Image` |
| `illustrationLoading` | `lazy` | `eager` charge l'illustration tout de suite |

| Bloc | |
|---|---|
| `content` | Plus de contenu sous les paragraphes |

## LoginForm

`<twig:LoginForm>` : une `section` de classe `isCard isLoginForm`, son formulaire d'éléments simples qui envoie les noms
de champs que lit le form login de Symfony Security. Le focus commence sur le nom d'utilisateur, ou sur le mot de passe
quand le nom d'utilisateur est réécrit ; l'erreur est une alerte active, que les deux champs nomment.

| Prop | Par défaut | |
|---|---|---|
| `action` | requis | Le `check_path` du pare-feu |
| `title`, `usernameLabel`, `passwordLabel`, `submitLabel` | requis | Les textes de la carte |
| `description` | `null` | Une phrase sous le titre |
| `id` | `'login'` | Le préfixe des identifiants des champs |
| `usernameField`, `passwordField` | `'_username'`, `'_password'` | Les noms des champs |
| `usernameType` | `'username'` | `email` pour un champ de courriel et son autocomplétion |
| `lastUsername` | `''` | Le nom d'utilisateur réécrit |
| `error` | `null` | Le message de la dernière tentative ratée, traduit ; vide vaut aucun |
| `csrfToken`, `csrfField` | `null`, `'_csrf_token'` | Le jeton CSRF et le nom de son champ caché |
| `rememberLabel`, `rememberField` | `null`, `'_remember_me'` | Une case « se souvenir de moi », laissée de côté sans son libellé |
| `forgotHref`, `forgotLabel` | `null` | Le lien vers un mot de passe oublié, écrit quand les deux sont donnés |
| `signupHref`, `signupLabel` | `null` | Le lien vers la page d'inscription, écrit quand les deux sont donnés |

| Bloc | |
|---|---|
| `content` | Plus de champs avant le bouton : un chemin cible caché, un captcha |

## SignupForm

`<twig:SignupForm>` : une `section` de classe `isCard isSignupForm` : un nom facultatif, l'adresse courriel, le nouveau
mot de passe et sa répétition facultative, chacun dans un `isField` avec ses erreurs, que le champ nomme par
`aria-describedby`. Les valeurs sont réécrites après une soumission refusée, jamais un mot de passe.

| Prop | Par défaut | |
|---|---|---|
| `action` | requis | L'adresse à laquelle le formulaire envoie |
| `title`, `emailLabel`, `passwordLabel`, `submitLabel` | requis | Les textes de la carte |
| `description` | `null` | Une phrase sous le titre |
| `id` | `'signup'` | Le préfixe des identifiants des champs |
| `nameLabel`, `confirmLabel` | `null` | Le champ du nom et le mot de passe répété, laissés de côté sans leurs libellés |
| `passwordHint` | `null` | La règle du mot de passe, sous son champ |
| `passwordMinLength` | `12` | Le `minlength` des champs de mot de passe |
| `nameField`, `emailField`, `passwordField`, `confirmField` | `'name'`, `'email'`, `'password'`, `'password_confirmation'` | Les noms des champs |
| `values` | `{}` | Les valeurs réécrites, par nom de champ |
| `errors` | `{}` | Les messages d'erreur, par nom de champ |
| `csrfToken`, `csrfField` | `null`, `'_token'` | Le jeton CSRF et le nom de son champ caché |
| `loginHref`, `loginLabel` | `null` | Le lien vers la page de connexion, écrit quand les deux sont donnés |

| Bloc | |
|---|---|
| `content` | Plus de champs avant le bouton : l'acceptation des conditions, un consentement |

## Les recettes du kit Shadcn

Ces recettes reprennent les noms, les props, les blocs et les contrôleurs Stimulus du kit Shadcn du Toolkit ; un bundle
en produit le rendu avec l'un ou l'autre kit. Leur HTML est bâti sur des éléments et sur les classes de phil-ux. Les
classes des tailles et des variantes de Shadcn : `default` est `isPrimary`, `secondary` `isSecondary`, `destructive`
`isDanger`, `outline` `isOutline`, `ghost` `isGhost` et `link` `isLink` ; `xs` et `sm` sont `isSmall`, `lg` `isLarge`,
les tailles `icon` `hasIcon isIconOnly`.
Les textes d'une recette sont des props ou des blocs, en anglais par défaut, à donner dans la langue de la page.

## Badge

Un court libellé : une étiquette, un état, un compte.

`<twig:Badge>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | La variante de style visuel. |
| `as` | `'span'` | La balise HTML à produire ; employez `a` quand la pastille mène ailleurs. |
| `color` | `null` | Une couleur CSS qui remplace la couleur de la variante ; tout ce qui n'est pas une couleur est laissé de côté. |

## Button

Un bouton, ou un lien qui en a l'air avec `as="a"`.

`<twig:Button>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | La variante de style visuel. |
| `size` | `'default'` | La taille du bouton. |
| `as` | `'button'` | La balise HTML à produire ; employez `a` quand le bouton mène ailleurs. |
| `loading` | `false` | Si une action est en cours : le bouton est marqué occupé. |

## ButtonGroup

Des boutons qui vont ensemble, joints : un sélecteur de vue, les périodes précédente et suivante.

Composants : `<twig:ButtonGroup>`, `<twig:ButtonGroup:Separator>`, `<twig:ButtonGroup:Text>`.

`<twig:ButtonGroup>`

| Prop | Par défaut | |
|---|---|---|
| `orientation` | `'horizontal'` | Le sens de disposition du groupe de boutons. |

`<twig:ButtonGroup:Separator>`

| Prop | Par défaut | |
|---|---|---|
| `orientation` | `'vertical'` | L'orientation du séparateur. |

`<twig:ButtonGroup:Text>`

| Prop | Par défaut | |
|---|---|---|
| `as` | `'div'` | La balise HTML à produire. |

## Card

Une carte : son image, un en-tête, son contenu et un pied.

Composants : `<twig:Card>`, `<twig:Card:Action>`, `<twig:Card:Content>`, `<twig:Card:Description>`,
`<twig:Card:Footer>`, `<twig:Card:Header>`, `<twig:Card:Title>`.

`<twig:Card>`

| Prop | Par défaut | |
|---|---|---|
| `size` | `'default'` | La variante de taille de la carte. |
| `as` | `'article'` | La balise HTML à produire. |

`<twig:Card:Title>`

| Prop | Par défaut | |
|---|---|---|
| `level` | `3` | Le niveau du titre, tenu entre 1 et 6. |

## Empty

Ce qu'une liste montre quand elle n'a rien : une image, un titre, une phrase, et quoi faire.

Composants : `<twig:Empty>`, `<twig:Empty:Content>`, `<twig:Empty:Description>`, `<twig:Empty:Header>`,
`<twig:Empty:Media>`, `<twig:Empty:Title>`.

`<twig:Empty:Media>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | Le style d'affichage du média. |

## Popover

Un panneau ouvert par un bouton, sur l'API popover du HTML : il s'ouvre et se ferme, Échap et un clic à l'extérieur le
ferment, sans JavaScript ; le contrôleur le place à côté de son bouton.

Composants : `<twig:Popover>`, `<twig:Popover:Content>`, `<twig:Popover:Trigger>`.

`<twig:Popover>`

| Prop | Par défaut | |
|---|---|---|
| `name` | `null` | Le nom de groupe que partagent des bulles mutuellement exclusives, pour qu'une seule soit ouverte à la fois. |
| `open` | `false` | Si la bulle est ouverte au premier rendu. |
| `id` | `null` | L'identifiant du panneau, produit quand il est omis. |

`<twig:Popover:Content>`

| Prop | Par défaut | |
|---|---|---|
| `side` | `'bottom'` | Le côté du déclencheur où le contenu apparaît. |
| `align` | `'center'` | L'alignement du contenu le long de l'axe transversal. |

Les éléments déclencheur et de fermeture prennent `popover_trigger_attrs` : `<twig:Button {{ ...popover_trigger_attrs
}}>`.

Le contrôleur `popover` :

| Nature | Nom | |
|---|---|---|
| Valeur | `open`, `name` | Comme les props |
| Cible | `trigger`, `content` | Le bouton et le panneau |
| Action | `toggle`, `close` | Ouvrir et fermer ; sur un invocateur, le navigateur le fait |
| Action | `sync` | Suit l'événement `toggle` du panneau : état, placement, focus |
| Action | `handleGroupOpen`, `handleOutsideClick`, `handleEscape` | Le groupe de bulles ; un clic à l'extérieur et Échap là où l'API popover manque |

## QrCode

Un code QR dans une figure, avec ce qu'il contient écrit à côté, un lien pour une adresse web, dessiné sur le serveur
par `phil/qr-code-bundle`.

`<twig:QrCode>`

| Prop | Par défaut | |
|---|---|---|
| `content` | requis | Un texte, ou le tableau d'un contenu typé du bundle. |
| `alt` | `''` | Le nom de l'image pour les lecteurs d'écran. |
| `caption` | `true` | Si ce que contient le code est écrit à côté. |
| `size` | `null` | La largeur et la hauteur de l'image en pixels ; la taille du bundle quand elle est nulle. |
| `level` | `null` | Le niveau de correction d'erreur, L, M, Q ou H ; le niveau du bundle quand il est nul. |
| `logo` | `''` | Le nom d'un logo de la configuration du bundle. |

## Tabs

Des onglets au-dessus de panneaux. Sans JavaScript, chaque panneau s'affiche ; avec lui, un à la fois, les flèches,
Début et Fin passant d'un onglet à l'autre.

Composants : `<twig:Tabs>`, `<twig:Tabs:Content>`, `<twig:Tabs:List>`, `<twig:Tabs:Trigger>`.

`<twig:Tabs>`

| Prop | Par défaut | |
|---|---|---|
| `defaultValue` | `''` | L'onglet ouvert au premier rendu ; le premier quand il est vide. |
| `orientation` | `'horizontal'` | L'orientation visuelle. |

`<twig:Tabs:Content>`

| Prop | Par défaut | |
|---|---|---|
| `value` | requis | Le suffixe unique qui sert à produire les identifiants internes de Tabs. |

`<twig:Tabs:List>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | La variante de style visuel. |

`<twig:Tabs:Trigger>`

| Prop | Par défaut | |
|---|---|---|
| `value` | requis | Le suffixe unique qui sert à produire les identifiants internes de Tabs. |

Le contrôleur `tabs` :

| Nature | Nom | |
|---|---|---|
| Valeur | `activeTab` | L'onglet affiché |
| Cible | `trigger`, `tab` | Les onglets et les panneaux |
| Action | `open` | Affiche l'onglet cliqué |
| Action | `move` | Les flèches, Début et Fin |

## Accordion

Des sections qui s'ouvrent et se ferment, sur `details` et `summary` : elles fonctionnent sans JavaScript, le navigateur
en gardant une seule ouverte à la fois ; le contrôleur ajoute les flèches, Début et Fin.

Composants : `<twig:Accordion>`, `<twig:Accordion:Content>`, `<twig:Accordion:Item>`, `<twig:Accordion:Trigger>`.

`<twig:Accordion>`

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant unique de l'accordéon. |
| `multiple` | `false` | Si plusieurs éléments peuvent être ouverts à la fois. |
| `defaultValue` | `null` | La ou les valeurs des éléments à ouvrir par défaut. |
| `orientation` | `'vertical'` | L'orientation visuelle, qui décide si la navigation au clavier emploie les flèches haut et bas ou gauche et droite. |

`<twig:Accordion:Item>`

| Prop | Par défaut | |
|---|---|---|
| `value` | requis | La valeur unique de cet élément d'accordéon. |
| `open` | `false` | Si l'élément est ouvert par défaut. |
| `disabled` | `false` | Si l'élément est désactivé. |

Le contrôleur `accordion` :

| Nature | Nom | |
|---|---|---|
| Valeur | `multiple`, `orientation` | Comme les props |
| Cible | `item`, `trigger`, `content` | Le `details`, le `summary` et le contenu |
| Action | `toggle` | Ouvre un élément depuis un autre contrôle que son résumé |
| Action | `handleKeydown` | Les flèches, Début et Fin |

## Dialog

Une fenêtre modale, sur l'élément natif `dialog` et ses invocateurs : le navigateur rend la page inerte, garde le focus
à l'intérieur, la ferme sur Échap et rend le focus.

Composants : `<twig:Dialog>`, `<twig:Dialog:Close>`, `<twig:Dialog:Content>`, `<twig:Dialog:Description>`,
`<twig:Dialog:Footer>`, `<twig:Dialog:Header>`, `<twig:Dialog:Title>`, `<twig:Dialog:Trigger>`.

`<twig:Dialog>`

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant unique qui sert à produire les identifiants internes de Dialog. |
| `open` | `false` | Si le dialogue est ouvert au premier rendu. |

`<twig:Dialog:Content>`

| Prop | Par défaut | |
|---|---|---|
| `showCloseButton` | `true` | S'il faut afficher le bouton de fermeture. |
| `closeLabel` | `'Close'` | Ce que lisent les lecteurs d'écran pour le bouton de fermeture. |

Les éléments déclencheur et de fermeture prennent `dialog_close_attrs`, `dialog_trigger_attrs` : `<twig:Button {{
...dialog_close_attrs }}>`.

Le contrôleur `dialog` :

| Nature | Nom | |
|---|---|---|
| Valeur | `open` | Comme la prop |
| Cible | `trigger`, `dialog` | Le bouton et l'élément |
| Action | `open`, `close` | L'afficher et le fermer, en modal |
| Action | `closeOnClickOutside`, `closed` | Un clic sur l'arrière-plan ; le déclencheur averti quand le navigateur l'a fermé |

## Sheet

Un panneau qui glisse depuis un bord de l'écran : un `Dialog` qui se tient d'un côté, avec le même contrôleur.

Composants : `<twig:Sheet>`, `<twig:Sheet:Close>`, `<twig:Sheet:Content>`, `<twig:Sheet:Description>`,
`<twig:Sheet:Footer>`, `<twig:Sheet:Header>`, `<twig:Sheet:Title>`, `<twig:Sheet:Trigger>`.

`<twig:Sheet>`

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant unique qui sert à produire les identifiants internes de Sheet. |
| `side` | `'right'` | Le bord depuis lequel le panneau glisse. |
| `open` | `false` | Si le panneau est ouvert au premier rendu. |

`<twig:Sheet:Content>`

| Prop | Par défaut | |
|---|---|---|
| `showCloseButton` | `true` | S'il faut afficher le bouton de fermeture. |
| `closeLabel` | `'Close'` | Ce que lisent les lecteurs d'écran pour le bouton de fermeture. |

Les éléments déclencheur et de fermeture prennent `sheet_close_attrs`, `sheet_trigger_attrs` : `<twig:Button {{
...sheet_close_attrs }}>`.

## Switch

Un contrôle marche-arrêt : une case à cocher au rôle `switch`, que le navigateur coche et soumet sans JavaScript.

`<twig:Switch>`

| Prop | Par défaut | |
|---|---|---|
| `size` | `'default'` | La taille de l'interrupteur. |
| `checked` | `false` | Si l'interrupteur est coché au départ. |

## Tooltip

Une courte description d'un contrôle, affichée au survol et au focus : on peut la survoler, elle reste jusqu'à ce qu'on
la quitte, et Échap la ferme (WCAG 1.4.13).

Composants : `<twig:Tooltip>`, `<twig:Tooltip:Content>`, `<twig:Tooltip:Trigger>`.

`<twig:Tooltip>`

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant unique de l'infobulle. |
| `delayDuration` | `0` | Le délai en millisecondes avant d'afficher l'infobulle. |

`<twig:Tooltip:Content>`

| Prop | Par défaut | |
|---|---|---|
| `side` | `'top'` | Le côté préféré pour afficher l'infobulle. |
| `sideOffset` | `0` | La distance en pixels au déclencheur. |

Les éléments déclencheur et de fermeture prennent `tooltip_trigger_attrs` : `<twig:Button {{ ...tooltip_trigger_attrs
}}>`.

Le contrôleur `tooltip` :

| Nature | Nom | |
|---|---|---|
| Valeur | `delayDuration`, `wrapperSelector`, `contentSelector`, `arrowSelector` | Le délai ; les éléments quand ils sont sortis du contrôleur |
| Cible | `trigger`, `wrapper`, `content`, `arrow` | Ses parties |
| Action | `show`, `hide` | Au survol et au focus, et sur l'infobulle elle-même |

## Pagination

Les liens entre les pages d'une liste.

Composants : `<twig:Pagination>`, `<twig:Pagination:Content>`, `<twig:Pagination:Ellipsis>`, `<twig:Pagination:Item>`,
`<twig:Pagination:Link>`, `<twig:Pagination:Next>`, `<twig:Pagination:Previous>`.

`<twig:Pagination>`

| Prop | Par défaut | |
|---|---|---|
| `label` | `'Pagination'` | Le nom de la navigation pour les lecteurs d'écran. |

`<twig:Pagination:Ellipsis>`

| Prop | Par défaut | |
|---|---|---|
| `text` | `'More pages'` | Ce que lisent les lecteurs d'écran à la place des points de suspension. |

`<twig:Pagination:Link>`

| Prop | Par défaut | |
|---|---|---|
| `active` | `false` | Si c'est la page courante. |
| `size` | `'icon'` | La taille du bouton. |

`<twig:Pagination:Next>`

| Prop | Par défaut | |
|---|---|---|
| `text` | `'Next'` | Le texte du libellé. |
| `label` | `'Go to next page'` | Ce que lisent les lecteurs d'écran pour le lien. |

`<twig:Pagination:Previous>`

| Prop | Par défaut | |
|---|---|---|
| `text` | `'Previous'` | Le texte du libellé. |
| `label` | `'Go to previous page'` | Ce que lisent les lecteurs d'écran pour le lien. |

## Carousel

Des diapositives en rangée, sur l'aimantation native du défilement : elles défilent au toucher, au pavé tactile et au
clavier sans JavaScript ; le contrôleur ajoute les boutons, les flèches et, sur demande, la lecture automatique.
`::scroll-button()` n'étant que dans un seul moteur, les boutons ne s'y fient pas.

Composants : `<twig:Carousel>`, `<twig:Carousel:Content>`, `<twig:Carousel:Item>`, `<twig:Carousel:Next>`,
`<twig:Carousel:Previous>`.

`<twig:Carousel>`

| Prop | Par défaut | |
|---|---|---|
| `orientation` | `'horizontal'` | L'axe le long duquel défilent les diapositives. |
| `align` | `'center'` | L'alignement de la diapositive choisie dans la zone visible. |
| `loop` | `false` | Si le carrousel reprend au début après la dernière diapositive. |
| `options` | `{}` | Des options en plus lues par le contrôleur, fusionnées par-dessus `align` et `loop`. |
| `autoplay` | `0` | Le délai en millisecondes entre deux changements automatiques de diapositive. Zéro désactive la lecture automatique. |
| `label` | `'Carousel'` | Le nom accessible de la région du carrousel. |
| `roleDescription` | `'carousel'` | Ce que le carrousel est annoncé comme étant. |
| `pauseLabel` | `'Pause'` | Le texte du bouton pendant que les diapositives défilent. |
| `playLabel` | `'Play'` | Le texte du bouton une fois en pause. |

`<twig:Carousel:Item>`

| Prop | Par défaut | |
|---|---|---|
| `roleDescription` | `'slide'` | Ce que la diapositive est annoncée comme étant. |

`<twig:Carousel:Next>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'outline'` | La variante de style du bouton. |
| `size` | `'icon-sm'` | La taille du bouton. |
| `text` | `'Next slide'` | Le libellé accessible du bouton. |

`<twig:Carousel:Previous>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'outline'` | La variante de style du bouton. |
| `size` | `'icon-sm'` | La taille du bouton. |
| `text` | `'Previous slide'` | Le libellé accessible du bouton. |

Le contrôleur `carousel` :

| Nature | Nom | |
|---|---|---|
| Valeur | `orientation`, `options`, `autoplay`, `pauseLabel`, `playLabel` | Comme les props, `loop` lu dans `options` |
| Cible | `viewport`, `previous`, `next`, `toggle` | La zone qui défile, les boutons, le bouton de pause |
| Action | `scrollPrev`, `scrollNext`, `handleKeydown`, `update` | Une diapositive à la fois, les flèches, les extrémités désactivées |
| Action | `hold`, `release`, `toggleAutoplay` | La lecture automatique retenue ou en pause |
| Événement | `carousel:select` | `{index, count}` quand la diapositive change |

## Spinner

Ce qui s'affiche pendant que quelque chose se charge ; phil-ux l'immobilise sous `prefers-reduced-motion`.

`<twig:Spinner>`

| Prop | Par défaut | |
|---|---|---|
| `label` | `'Loading'` | Le libellé accessible annoncé aux technologies d'assistance. |

## Skeleton

Un espace réservé pour un contenu en route, caché aux lecteurs d'écran ; phil-ux l'immobilise sous
`prefers-reduced-motion`.

## Alert

Un message qui se démarque : une erreur, une confirmation.

Composants : `<twig:Alert>`, `<twig:Alert:Action>`, `<twig:Alert:Description>`, `<twig:Alert:Title>`.

`<twig:Alert>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | La variante de style visuel. |

## Avatar

L'image d'une personne, par-dessus les initiales qui s'affichent quand elle manque ou est brisée, sans script.

Composants : `<twig:Avatar>`, `<twig:Avatar:Badge>`, `<twig:Avatar:Fallback>`, `<twig:Avatar:Group>`,
`<twig:Avatar:GroupCount>`, `<twig:Avatar:Image>`.

`<twig:Avatar>`

| Prop | Par défaut | |
|---|---|---|
| `size` | `'default'` | La taille de l'avatar. |

## Input

Un champ de texte ; tout attribut atteint l'élément.

## Label

Le nom d'un contrôle de formulaire.

## Select

Une liste d'options parmi lesquelles en choisir une, soumise comme un champ du formulaire quand elle a un `name`. Pour
une page sans JavaScript, l'élément `select` est habillé par phil-ux à lui seul.

Composants : `<twig:Select>`, `<twig:Select:Content>`, `<twig:Select:Group>`, `<twig:Select:Item>`,
`<twig:Select:Label>`, `<twig:Select:Separator>`, `<twig:Select:Trigger>`, `<twig:Select:Value>`.

`<twig:Select>`

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant unique posé sur le déclencheur, pour qu'un `<label for>` puisse le désigner, et qui sert à produire les identifiants internes de Select. |
| `name` | `null` | Le nom du champ de formulaire qui porte la valeur choisie. |
| `value` | `''` | La valeur choisie au premier rendu. |
| `disabled` | `false` | Si la liste est désactivée. |
| `required` | `false` | Si une valeur doit être choisie avant que le formulaire puisse être soumis. |
| `open` | `false` | Si la liste est ouverte au premier rendu. |

`<twig:Select:Content>`

| Prop | Par défaut | |
|---|---|---|
| `position` | `'item-aligned'` | Comment le contenu est placé : par-dessus le déclencheur sur l'élément choisi, ou ancré au bord du déclencheur. |
| `side` | `'bottom'` | Le côté du déclencheur où le contenu apparaît. |
| `align` | `'start'` | L'alignement du contenu le long de l'axe transversal. |

`<twig:Select:Group>`

| Prop | Par défaut | |
|---|---|---|
| `id` | `null` | L'identifiant unique qui lie le groupe à son `Select:Label`, pour que le groupe soit annoncé avec un nom. |

`<twig:Select:Item>`

| Prop | Par défaut | |
|---|---|---|
| `value` | requis | La valeur soumise quand cet élément est choisi. |
| `disabled` | `false` | Si l'élément est désactivé. |

`<twig:Select:Trigger>`

| Prop | Par défaut | |
|---|---|---|
| `size` | `'default'` | La taille du déclencheur. |

`<twig:Select:Value>`

| Prop | Par défaut | |
|---|---|---|
| `placeholder` | `''` | Le texte affiché tant qu'aucune option n'est choisie. |

Le contrôleur `select` :

| Nature | Nom | |
|---|---|---|
| Valeur | `open`, `value` | Comme les props |
| Cible | `trigger`, `value`, `content`, `viewport`, `item`, `hiddenSelect`, `scrollUpButton`, `scrollDownButton` | Ses parties |
| Action | `toggle`, `sync`, `selectItem`, `highlightItem`, `unhighlightItem`, `onTriggerKeydown`, `onContentKeydown`, `scrollUp`, `scrollDown`, `onViewportScroll` | L'ouverture, le choix, le clavier et le défilement |
| Événement | `select:change` | `{value, label}`, et un `change` sur le champ caché |

## Separator

Une ligne entre deux parties d'une page.

`<twig:Separator>`

| Prop | Par défaut | |
|---|---|---|
| `orientation` | `'horizontal'` | L'orientation du séparateur. |
| `decorative` | `true` | Si le séparateur est purement décoratif (sans sens). |

## Textarea

Un champ de plusieurs lignes.

## Checkbox

Une case à cocher, dessinée par phil-ux.

## Breadcrumb

Où la page se situe dans le site.

Composants : `<twig:Breadcrumb>`, `<twig:Breadcrumb:Ellipsis>`, `<twig:Breadcrumb:Item>`, `<twig:Breadcrumb:Link>`,
`<twig:Breadcrumb:List>`, `<twig:Breadcrumb:Page>`, `<twig:Breadcrumb:Separator>`.

`<twig:Breadcrumb>`

| Prop | Par défaut | |
|---|---|---|
| `label` | `'Breadcrumb'` | Le nom de la navigation pour les lecteurs d'écran. |

`<twig:Breadcrumb:Ellipsis>`

| Prop | Par défaut | |
|---|---|---|
| `text` | `'More'` | Ce que lisent les lecteurs d'écran à la place des points de suspension. |

## DropdownMenu

Un menu d'actions ouvert par un bouton, sur l'API popover : Échap et un clic à l'extérieur le ferment et le focus
retourne au bouton.

Le clavier suit WAI-ARIA et diffère du kit Shadcn de deux façons : un sous-menu s'ouvre à la flèche vers son côté de fin
(la flèche droite, ou la gauche de droite à gauche) et pas seulement à Entrée ; et Échap sur un dialogue est laissé au
navigateur, qui ne ferme que celui du dessus quand des dialogues sont imbriqués, au lieu de tous les fermer. Échap dans
un sous-menu ferme ce sous-menu et rend le focus à l'élément qui l'a ouvert.

Composants : `<twig:DropdownMenu>`, `<twig:DropdownMenu:CheckboxItem>`, `<twig:DropdownMenu:Content>`,
`<twig:DropdownMenu:Group>`, `<twig:DropdownMenu:Item>`, `<twig:DropdownMenu:Label>`, `<twig:DropdownMenu:RadioGroup>`,
`<twig:DropdownMenu:RadioItem>`, `<twig:DropdownMenu:Separator>`, `<twig:DropdownMenu:Shortcut>`,
`<twig:DropdownMenu:Sub>`, `<twig:DropdownMenu:SubContent>`, `<twig:DropdownMenu:SubTrigger>`,
`<twig:DropdownMenu:Trigger>`.

`<twig:DropdownMenu>`

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant unique qui sert à produire les identifiants internes de DropdownMenu. |
| `open` | `false` | Si le menu est ouvert au premier rendu. |
| `side` | `'bottom'` | Le côté du déclencheur où le menu apparaît. |
| `align` | `'start'` | L'alignement du menu le long de l'axe transversal. |

`<twig:DropdownMenu:CheckboxItem>`

| Prop | Par défaut | |
|---|---|---|
| `checked` | `false` | Si la case est cochée au départ. |
| `disabled` | `false` | Si l'élément est désactivé. |
| `closeOnSelect` | `false` | Si choisir l'élément ferme le menu. |

`<twig:DropdownMenu:Item>`

| Prop | Par défaut | |
|---|---|---|
| `as` | `'button'` | La balise HTML à produire. |
| `disabled` | `false` | Si l'élément est désactivé. |
| `inset` | `false` | Si l'élément est en retrait, avec un remplissage de début en plus. |
| `variant` | `'default'` | La variante de style visuel. |
| `closeOnSelect` | `true` | Si choisir l'élément ferme le menu. |

`<twig:DropdownMenu:Label>`

| Prop | Par défaut | |
|---|---|---|
| `inset` | `false` | Si le libellé est en retrait, avec un remplissage de début en plus. |

`<twig:DropdownMenu:RadioGroup>`

| Prop | Par défaut | |
|---|---|---|
| `value` | `null` | La valeur actuellement choisie. |

`<twig:DropdownMenu:RadioItem>`

| Prop | Par défaut | |
|---|---|---|
| `value` | requis | La valeur soumise quand ce bouton radio est choisi. |
| `checked` | `false` | Si ce bouton radio est coché au départ. |
| `disabled` | `false` | Si l'élément est désactivé. |
| `closeOnSelect` | `false` | Si choisir l'élément ferme le menu. |

`<twig:DropdownMenu:Sub>`

| Prop | Par défaut | |
|---|---|---|
| `openDelay` | `0` | Le délai en millisecondes avant d'afficher le sous-menu. |
| `closeDelay` | `0` | Le délai en millisecondes avant de cacher le sous-menu. |

`<twig:DropdownMenu:SubTrigger>`

| Prop | Par défaut | |
|---|---|---|
| `inset` | `false` | Si le déclencheur est en retrait, avec un remplissage de début en plus. |

Les éléments déclencheur et de fermeture prennent `dropdown_menu_trigger_attrs` : `<twig:Button {{
...dropdown_menu_trigger_attrs }}>`.

Le contrôleur `dropdown-menu, dropdown-menu-sub` :

| Nature | Nom | |
|---|---|---|
| Valeur | open ; openDelay, closeDelay | Comme les props |
| Cible | `trigger`, `content`, `item` | Le menu ; `trigger` et `content` pour un sous-menu |
| Action | `toggle`, `sync`, `closeFromItem`, `toggleCheckbox`, `selectRadio`, `onTriggerKeydown`, `onContentKeydown` | Le menu et les rôles de ses éléments |
| Action | `show`, `hide`, `sync` | Le sous-menu |

## Table

Un tableau de données, qui défile de côté dans sa propre boîte sur un petit écran.

Composants : `<twig:Table>`, `<twig:Table:Body>`, `<twig:Table:Caption>`, `<twig:Table:Cell>`, `<twig:Table:Footer>`,
`<twig:Table:Head>`, `<twig:Table:Header>`, `<twig:Table:Row>`.

## AlertDialog

Une question qui interrompt, dans une fenêtre modale sur l'élément natif `dialog` au rôle `alertdialog` : Échap la
ferme, comme une demande de fermeture (`closedby="closerequest"`), un clic sur l'arrière-plan non, et le focus commence
sur le bouton d'annulation. Elle tourne sur le contrôleur `dialog`, que la recette installe avec la recette `Dialog`.

Composants : `<twig:AlertDialog>`, `<twig:AlertDialog:Action>`, `<twig:AlertDialog:Cancel>`,
`<twig:AlertDialog:Content>`, `<twig:AlertDialog:Description>`, `<twig:AlertDialog:Footer>`,
`<twig:AlertDialog:Header>`, `<twig:AlertDialog:Title>`, `<twig:AlertDialog:Trigger>`.

`<twig:AlertDialog>`

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant unique qui sert à produire les identifiants internes d'AlertDialog. |
| `open` | `false` | Si le dialogue d'alerte est ouvert au premier rendu. |

`<twig:AlertDialog:Action>` et `<twig:AlertDialog:Cancel>` : des boutons qui ferment le dialogue d'alerte, de variante
`default` et `outline` ; tout autre attribut atteint le bouton, comme `type="submit"` et `form`.

L'élément déclencheur prend `alert_dialog_trigger_attrs` : `<twig:Button {{ ...alert_dialog_trigger_attrs }}>`.

## AspectRatio

Une boîte tenue à un rapport, son image ou sa vidéo la couvrant.

`<twig:AspectRatio>`

| Prop | Par défaut | |
|---|---|---|
| `ratio` | `1` | Le rapport de la largeur à la hauteur, un nombre ou un quotient comme `"16 / 9"` ; tout le reste donne un carré |

## Collapsible

Une partie de la page qui s'ouvre et se ferme, sur `details` et `summary`, sans JavaScript : le résumé est le
déclencheur.

Composants : `<twig:Collapsible>`, `<twig:Collapsible:Content>`, `<twig:Collapsible:Trigger>`.

`<twig:Collapsible>`

| Prop | Par défaut | |
|---|---|---|
| `open` | `false` | Si le repliable est ouvert au premier rendu. |
| `disabled` | `false` | Si le repliable est désactivé. |

## Field

Un champ de formulaire — son libellé, son contrôle, son aide et ses erreurs — et des ensembles de champs sous une
légende.

Composants : `<twig:Field>`, `<twig:Field:Content>`, `<twig:Field:Description>`, `<twig:Field:Error>`,
`<twig:Field:Group>`, `<twig:Field:Label>`, `<twig:Field:Legend>`, `<twig:Field:Separator>`, `<twig:Field:Set>`,
`<twig:Field:Title>`.

`<twig:Field>`

| Prop | Par défaut | |
|---|---|---|
| `orientation` | `'vertical'` | `vertical`, `horizontal`, ou `responsive`, horizontal sur un conteneur de 40rem ou plus |
| `invalid` | `false` | Si le champ contient une valeur invalide : il prend `isDanger` et `data-invalid` |

`<twig:Field:Error>` : une alerte active, écrite seulement quand il y a une erreur : un paragraphe pour une, une liste
pour plusieurs, son bloc à la place de `errors`.

| Prop | Par défaut | |
|---|---|---|
| `errors` | `[]` | Les messages d'erreur, chacun échappé |

`<twig:Field:Legend>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'legend'` | `legend`, ou `label` pour la taille d'un libellé |

## Item

Une entrée d'une liste : son média, son titre et sa description, ses actions.

Composants : `<twig:Item>`, `<twig:Item:Actions>`, `<twig:Item:Content>`, `<twig:Item:Description>`,
`<twig:Item:Footer>`, `<twig:Item:Group>`, `<twig:Item:Header>`, `<twig:Item:Media>`, `<twig:Item:Separator>`,
`<twig:Item:Title>`.

`<twig:Item>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | `default`, `outline` ou `muted` |
| `size` | `'default'` | `default` ou `sm` |
| `as` | `'div'` | `div`, `a`, ou `li` dans un `Item:Group`, qui est une liste |

`<twig:Item:Media>`, caché aux lecteurs d'écran

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | `default`, `icon` ou `image` |

## Kbd

Une touche du clavier, sur l'élément natif `kbd`, et les touches d'un raccourci.

Composants : `<twig:Kbd>`, `<twig:Kbd:Group>`.

## NativeSelect

L'élément `select` du navigateur, habillé par phil-ux.

Composants : `<twig:NativeSelect>`, `<twig:NativeSelect:OptGroup>`, `<twig:NativeSelect:Option>`.

| Prop | Par défaut | |
|---|---|---|
| `size` | `'default'` | `default` ou `sm`, sur `<twig:NativeSelect>` |
| `label` | requis | Le nom du groupe, sur `<twig:NativeSelect:OptGroup>` |

## Progress

La progression d'une tâche, sur l'élément natif `progress`.

`<twig:Progress>`

| Prop | Par défaut | |
|---|---|---|
| `value` | `null` | La progression, tenue entre 0 et le maximum ; nulle tant qu'elle est inconnue |
| `max` | `100` | La valeur d'une tâche terminée ; 100 quand elle n'est pas au-dessus de 0 |

## RadioGroup

Des boutons radio qui partagent un nom, dans un élément de rôle `radiogroup` : le navigateur en coche un à la fois,
passe de l'un à l'autre avec les flèches et le soumet.

Composants : `<twig:RadioGroup>`, `<twig:RadioGroup:Item>`.

`<twig:RadioGroup>`

| Prop | Par défaut | |
|---|---|---|
| `name` | requis | Le nom du champ de formulaire que partagent les boutons radio |
| `value` | `null` | La valeur cochée au premier rendu |
| `orientation` | `'vertical'` | `vertical` ou `horizontal` |
| `disabled`, `required` | `false` | Posé sur chaque bouton radio |

`<twig:RadioGroup:Item>` : un `input` de type `radio`, nommé par un `Label` ; `value` requis, `disabled`.

## ScrollArea

Une boîte dont le contenu défile à l'intérieur, avec des barres de défilement fines.

`<twig:ScrollArea>`

| Prop | Par défaut | |
|---|---|---|
| `orientation` | `'vertical'` | `vertical`, `horizontal` ou `both` |
| `label` | `null` | Le nom de la région ; donné, la boîte est une région que le clavier atteint |

## Toggle

Un bouton qui reste enfoncé, sur une case à cocher dans son libellé : le navigateur l'enfonce et le soumet avec le
formulaire, sans JavaScript.

`<twig:Toggle>`

| Prop | Par défaut | |
|---|---|---|
| `variant` | `'default'` | `default` ou `outline` |
| `size` | `'default'` | `default`, `sm` ou `lg` |
| `pressed`, `disabled` | `false` | L'état au premier rendu |
| `name` | `null` | Le nom du champ de formulaire |
| `value` | `'on'` | La valeur soumise tant qu'il est enfoncé |

## ToggleGroup

Des boutons à bascule qui vont ensemble : des boutons radio pour un choix, des cases à cocher pour plusieurs, dans un
groupe nommé par `label`.

Composants : `<twig:ToggleGroup>`, `<twig:ToggleGroup:Item>`.

`<twig:ToggleGroup>`

| Prop | Par défaut | |
|---|---|---|
| `name`, `label` | requis | Le nom de champ que partagent les boutons à bascule et le nom du groupe |
| `type` | `'single'` | `single`, ou `multiple`, dont le nom de champ se termine par `[]` |
| `value` | `null` | La valeur, ou les valeurs, enfoncées au premier rendu |
| `variant`, `size` | `'default'` | Comme sur `Toggle` |
| `disabled` | `false` | Posé sur chaque bouton à bascule |

`<twig:ToggleGroup:Item>` : `value` requis, `disabled`.

## Les recettes de ce kit seulement

`Logo`, `AnimatedHeadline`, `Illustration`, `Gallery`, `Lightbox`, `Hotspots`, `Hero`, `Video`, `Figure`,
`AutosaveStatus`, `ReactionButton`, `ReactionPicker`, `QrCode`, les blocs `ComingSoon`, `LoginForm` et `SignupForm`,
les deux graphiques et les recettes ci-dessous n'ont pas d'équivalent dans le kit Shadcn. Sur Tailwind, le kit
`phil/ux-kit-shadcn` contient chacun d'eux sous les mêmes noms, props, blocs et contrôleurs.

## Avec le kit Common

Les recettes du kit Common du Toolkit (`clipboard`, `closeable`, `password-visibility`, `post-link`, `logout-link`,
`sortable`, `tooltip`) s'emploient telles quelles, depuis ce kit ; ce kit-ci ne les copie pas.

```sh
php bin/console ux:install clipboard --kit=common
```

## ColorPicker

`<twig:ColorPicker>` : un `div` de classe `isColorPicker` : le sélecteur de couleur, son texte et ses pastilles.
Contrôleur `color-picker` : les actions `pick`, `type` et `choose`, les cibles `well`, `text` et `swatch`.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Le nom par lequel le sélecteur de couleur est lu |
| `id` | `null` | L'identifiant du champ |
| `name` | `null` | Le nom du champ |
| `value` | `#000000` | La couleur, en `#rrggbb` ; tout le reste donne du noir |
| `swatches` | `[]` | Les couleurs parmi lesquelles choisir : `#rrggbb`, ou des tableaux de `value` et `label` |
| `valueLabel` | `Hexadecimal value` | Le nom du champ de texte |
| `swatchesLabel` | `Suggested colours` | Le nom du groupe de pastilles |
| `disabled` | `false` | Si le champ ne peut pas être changé pour l'instant |

## DatePicker

`<twig:DatePicker>` : un `div` de classe `isDatePicker` : le champ de date, son bouton et le mois dans une bulle.
Contrôleur `date-picker` : les actions `toggled`, `navigate`, `choose`, `previous` et `next`, les cibles `input`,
`trigger`, `panel`, `heading`, `weekdays` et `weeks`, les classes `day` et `weekday`, écrites par le gabarit sur ce que
crée le script.

| Prop | Par défaut | |
|---|---|---|
| `id` | requis | L'identifiant du champ, d'où sont faits ceux du panneau |
| `name` | `null` | Le nom du champ |
| `value` | `null` | La date, en `YYYY-MM-DD` |
| `min` | `null` | La première date qui peut être choisie |
| `max` | `null` | La dernière date qui peut être choisie |
| `label` | `null` | Ce que les lecteurs d'écran appellent le champ, sans `label` à côté |
| `locale` | `null` | La langue du mois ; celle de la page quand elle est nulle |
| `firstDay` | `null` | Le premier jour de la semaine, 0 pour dimanche ; celui de la langue quand il est nul |
| `openLabel` | `Choose a date` | Le nom du bouton qui ouvre le mois |
| `previousLabel` | `Previous month` | Le nom du bouton qui recule d'un mois |
| `nextLabel` | `Next month` | Le nom du bouton qui avance d'un mois |
| `required` | `false` | Si une date doit être donnée |
| `disabled` | `false` | Si le champ ne peut pas être changé pour l'instant |

## CopyButton

`<twig:CopyButton>` : un `span` de classe `isCopyButton` : le bouton et sa région active. Contrôleur `copy-button` :
l'action `copy`, l'événement `copy-button:copied`.

| Prop | Par défaut | |
|---|---|---|
| `text` | `null` | Le texte à copier |
| `source` | `null` | Le sélecteur CSS de l'élément dont la valeur ou le texte est copié, quand `text` est nul |
| `label` | `Copy` | Le nom du bouton |
| `copiedLabel` | `Copied` | Ce que dit le bouton une fois le texte copié |
| `failedLabel` | `Copy failed` | Ce que dit le bouton quand le navigateur refuse |
| `duration` | `2000` | Combien de temps le résultat s'affiche, en millisecondes |

## Celebration

`<twig:Celebration>` : un `div` de classe `isCelebration` : ce qui la joue, dans son bloc `content`, le paragraphe de
classe `isCelebrationMessage`, une région active polie, et le `template` de son canvas, de classe
`isCelebrationCanvas`, caché aux lecteurs d'écran. Contrôleur `celebration` : l'action `play`, dont les paramètres
`message` et `effect` remplacent les props ; l'événement DOM `celebrate`, émis sur n'importe quel élément ou sur la
fenêtre, dont le `detail` peut donner un `message` et un `effect` ; les événements `celebration:start` et
`celebration:end`. Là où le mouvement est réduit, par le système ou par `data-motion="reduce"` sur un ancêtre, le
message seul. Une seule animation à la fois sur la page ; le canvas posé sur la page par le CSSOM, sans prendre de clic,
et retiré à la fin ou quand l'élément quitte la page ; les couleurs celles de `--phil-color-brand-fill-loud`,
`--phil-color-success-fill-loud`, `--phil-color-warning-fill-loud`, `--phil-color-danger-fill-loud` et
`--phil-color-info-fill-loud`, résolues là où se trouve le canvas.

| Prop | Par défaut | |
|---|---|---|
| `message` | `''` | Les mots de la victoire, dits quand elle joue, et affichés là où le mouvement est réduit |
| `effect` | `confetti` | Ce qui est dessiné, `confetti` ou `fireworks` ; `confetti` pour tout autre nom |
| `autoplay` | `false` | Si elle joue dès que la page l'affiche, le message écrit dans la page dès le départ |
| `duration` | `2500` | La durée de l'animation, en millisecondes, que le contrôleur garde entre 600 et 4000 |

## AnimatedButton

`<twig:AnimatedButton>` : un `button`, ou un `a` avec `href`, de classe `isAnimatedButton` ; aucun contrôleur.

| Prop | Par défaut | |
|---|---|---|
| `effect` | `shine` | Comment il bouge |
| `href` | `null` | Où mène le lien ; un bouton quand il est nul |
| `type` | `button` | Le type du bouton |
| `loading` | `false` | Si ce qu'il a lancé est encore en cours |

## Stepper

`<twig:Stepper>` : un `ol` de classe `isStepper`, qui contient des `<twig:Stepper:Item>`, un `li` de classe
`isStepperItem`.

| Prop | Par défaut | |
|---|---|---|
| `label` | `null` | Ce que les lecteurs d'écran appellent la liste des étapes |

`<twig:Stepper:Item>` :

| Prop | Par défaut | |
|---|---|---|
| `state` | `upcoming` | Où en est le processus à cette étape |
| `href` | `null` | Où mène l'étape, quand on peut y revenir |
| `doneLabel` | `null` | Ce que lisent les lecteurs d'écran avant le nom d'une étape terminée |

## ActivityCalendar

`<twig:ActivityCalendar>` : un `div` de classe `hasActivityCalendar`, qui contient le `ol` de classe
`isActivityCalendar` et la légende.

| Prop | Par défaut | |
|---|---|---|
| `days` | requis | Les jours dans l'ordre, chacun un tableau de `level`, de 0 à 4, et de `label` |
| `label` | requis | Ce que les lecteurs d'écran appellent le calendrier |
| `offset` | `0` | Les cases vides avant le premier jour, de 0 à 6, pour qu'il tombe sur son jour de la semaine |
| `lessLabel` | `null` | Le mot du bas de la légende, qui demande les deux bouts |
| `moreLabel` | `null` | Le mot du haut de la légende, qui demande les deux bouts |

## Tree

`<twig:Tree>` : un `ul` de classe `isTree`, qui contient des `<twig:Tree:Item>`, un `li` de classe `isTreeItem`.
Contrôleur `tree` : les actions `navigate` et `activate`, les cibles `item`, `toggle` et `link`, l'événement
`tree:select`, dont le `detail.value` est la valeur de l'élément.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Ce que les lecteurs d'écran appellent l'arbre |

`<twig:Tree:Item>` :

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Le nom de l'élément |
| `value` | `null` | Ce que porte l'événement `tree:select` ; le nom quand il est nul |
| `branch` | `false` | Si l'élément en contient d'autres, écrits comme son contenu |
| `expanded` | `false` | Si la branche montre ce qu'elle contient au départ |
| `selected` | `false` | Si l'élément est celui qui est choisi |
| `href` | `null` | Où mène l'élément |

## Rating

`<twig:Rating>` : un `fieldset` de boutons radio avec un `name`, un `span` de `role="img"` sans lui, tous deux de classe
`isRating` ; aucun contrôleur.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Ce qui est noté |
| `name` | `null` | Le nom du champ ; en lecture seule quand il est nul |
| `value` | `0` | La valeur |
| `max` | `5` | Combien d'étoiles, jusqu'à dix |
| `starLabel` | `{value} of {max}` | Ce que lisent les lecteurs d'écran pour une note, `{value}` et `{max}` remplacés |
| `clearLabel` | `null` | Le choix d'aucune étoile, qui rend la note facultative ; aucun quand il est nul |
| `required` | `false` | Si une note doit être donnée |
| `disabled` | `false` | Si la note ne peut pas être changée pour l'instant |

## NumberInput

`<twig:NumberInput>` : un `div` de classe `isNumberInput`. Contrôleur `number-input` : les actions `decrement`,
`increment` et `refresh`.

| Prop | Par défaut | |
|---|---|---|
| `id` | `null` | L'identifiant du champ, pour un `label` à côté |
| `name` | `null` | Le nom du champ |
| `value` | `null` | La valeur |
| `min` | `null` | La plus petite valeur |
| `max` | `null` | La plus grande valeur |
| `step` | `1` | Le pas, ou `any` |
| `label` | `null` | Ce que les lecteurs d'écran appellent le champ, sans `label` à côté |
| `decrementLabel` | `Decrease` | Le nom du bouton qui baisse la valeur |
| `incrementLabel` | `Increase` | Le nom du bouton qui monte la valeur |
| `required` | `false` | Si une valeur doit être donnée |
| `disabled` | `false` | Si le champ ne peut pas être changé pour l'instant |

## FileInput

`<twig:FileInput>` : un `div` de classe `isFileInput`. Contrôleur `file-input` : les actions `over`, `leave`, `drop`,
`list` et `clear`, les classes `item` et `dragging`. Ses limites servent le lecteur ; le serveur valide ce qu'il reçoit.

| Prop | Par défaut | |
|---|---|---|
| `name` | requis | Le nom du champ |
| `label` | requis | Ce que dit la zone |
| `hint` | `null` | Une seconde ligne dans la zone : les types et la taille attendus |
| `id` | `null` | L'identifiant du champ |
| `accept` | `null` | Les types acceptés, tels que l'attribut `accept` les écrit |
| `multiple` | `false` | Si plusieurs fichiers peuvent être donnés |
| `maxSize` | `0` | Le plus gros fichier accepté, en octets ; 0 pour aucune limite |
| `rejectedLabel` | `Files left out: {count}` | Ce qui est dit des fichiers laissés de côté, `{count}` remplacé |
| `clearLabel` | `Remove` | Le bouton qui vide le champ |
| `required` | `false` | Si un fichier doit être donné |
| `disabled` | `false` | Si le champ ne peut pas être changé pour l'instant |

## Tag

`<twig:Tag>` : un `span` de classe `isTag`. Contrôleur `tag`, quand elle peut être retirée : l'action `remove`,
l'événement `tag:remove`, qui peut être annulé et dont le `detail.name` est la valeur de l'étiquette.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Ce que dit l'étiquette |
| `removable` | `false` | Si elle peut être retirée |
| `name` | `null` | Le nom d'un champ caché qui porte l'étiquette dans un formulaire |
| `value` | `null` | Ce que portent le champ et l'événement ; le libellé quand il est nul |
| `removeLabel` | `Remove {label}` | Le nom du bouton, `{label}` remplacé |

## RelativeTime

`<twig:RelativeTime>` : un `time` de classe `isRelativeTime`. Contrôleur `relative-time`, sur une seule minuterie,
relancée quand la page revient.

| Prop | Par défaut | |
|---|---|---|
| `datetime` | requis | Le moment |
| `format` | `Y-m-d H:i` | La date écrite par le serveur, au format du filtre `date` |
| `locale` | `null` | La langue du texte ; celle de la page quand elle est nulle |
| `length` | `long` | La longueur des mots |
| `limit` | `0` | Jusqu'à quel âge un moment reste relatif, en secondes ; 0 pour toujours |

## ProgressRing

`<twig:ProgressRing>` : un `div` de `role="progressbar"` et de classe `isProgressRing` ; aucun contrôleur.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Ce qui progresse |
| `value` | `null` | Où il en est ; inconnu quand il est nul |
| `max` | `100` | La valeur à la fin |
| `text` | `null` | Ce que dit le centre à la place du pourcentage |

## Sparkline

`<twig:Sparkline>` : un `svg` de `role="img"` et de classe `isSparkline`, dessiné par le serveur ; rien n'est écrit sans
valeurs. Les 365 dernières valeurs sont gardées.

| Prop | Par défaut | |
|---|---|---|
| `values` | requis | Les valeurs, les plus anciennes d'abord |
| `label` | requis | Ce que dit la ligne, lu par les lecteurs d'écran |
| `width` | `96` | La largeur du dessin |
| `height` | `24` | La hauteur du dessin |
| `area` | `false` | Si l'aire sous la ligne est remplie |

## BeforeAfter

`<twig:BeforeAfter>` : un `div` de classe `isBeforeAfter`, avec les blocs `before` et `after`. Contrôleur `before-after`
: l'action `move`, les cibles `range` et `before`.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Ce que déplace le curseur, lu par les lecteurs d'écran |
| `position` | `50` | Où commence la coupe, en pourcentage |

## Chart

`<twig:Chart>` : une `figure` de classe `isChart`, sa légende, le dessin en SVG caché aux lecteurs d'écran, une légende
des séries quand il y en a plusieurs, et le tableau des valeurs. L'échelle est arrondie à des pas de 1, 2 ou 5 fois une
puissance de dix, de quatre à six quelles que soient les valeurs ; une valeur absente coupe la ligne, une valeur seule
entre deux absentes est un point. Les 366 dernières étiquettes et les six premières séries sont gardées. Chaque série
prend une tonalité, `isPrimary`, `isInfo`, `isSuccess`, `isWarning`, `isDanger` puis `isNeutral`, et à partir de la
deuxième, sa ligne un tiret à elle.

| Prop | Par défaut | |
|---|---|---|
| `label` | requis | Ce que montre le graphique, sa légende |
| `labels` | requis | Les étiquettes le long de l'axe horizontal, les plus anciennes d'abord |
| `series` | requis | Les séries, chacune un tableau de son `label` et de ses `values` |
| `type` | `'bar'` | `bar` ou `line` |
| `width`, `height` | `640`, `320` | La taille du dessin, qui rétrécit à sa boîte |
| `tableLabel` | `null` | Le résumé d'un dévoilement qui montre le tableau ; sans lui, seuls les lecteurs d'écran le lisent |

## ChartJs

`<twig:ChartJs>` : une `figure` de classe `isChart hasChartJs`, le canevas que dessine Symfony UX Chart.js, caché aux
lecteurs d'écran, et le tableau des mêmes valeurs lu dans les données du graphique. Chart.js, ses options et ses
extensions sont ceux de l'application ; la recette dépend de `symfony/ux-chartjs`, qu'ajoute `ux:install`.

| Prop | Par défaut | |
|---|---|---|
| `chart` | requis | Le `Symfony\UX\Chartjs\Model\Chart` que l'application a bâti |
| `label` | requis | Ce que montre le graphique, sa légende |
| `tableLabel` | `null` | Le résumé d'un dévoilement qui montre le tableau ; sans lui, seuls les lecteurs d'écran le lisent |
