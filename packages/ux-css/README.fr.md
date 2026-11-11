<!-- phil-dev source-sha256=3bf4ffdfe814dd7ad71ad6f1b00fd3e2a69d961d86a28393ddbe5d2de5f8f020 -->
# phil/ux-css

**phil-ux, le cadriciel CSS de PHIL : du vrai CSS, presque sans classes, avec des jetons `--phil-*`, clair et sombre.**

phil-ux habille d'abord les éléments HTML sans classe — la page, sa typographie, les formulaires tels que Symfony les
produit, les boutons, les tableaux, les dialogues, les dévoilements, la navigation, les jauges et les bulles — et ajoute
des classes qui disent ce qu'un élément est ou ce qu'il a, en camelCase : `isSplit`, `isDanger`, `hasIcon`. C'est du CSS
simple en couches en cascade, sans étape de construction et sans Tailwind : les styles d'un site, écrits hors de toute
couche, l'emportent sur lui sans `!important`.

Chaque valeur est un jeton. Les échelles sont nommées en tailles de t-shirt et suivent des multiplicateurs globaux. Les
couleurs viennent d'une palette OKLCH numérotée selon la clarté, et les composants lisent des rôles de couleur — un
remplissage, une bordure et la couleur sur ce remplissage, chacun discret ou fort —, jamais la palette. Un seul thème
porte le clair et le sombre dans les mêmes déclarations ; la page suit le système à moins que `data-theme` ne choisisse.
Le paquet fournit aussi la règle de contraste en PHP contre laquelle le thème est testé, qu'un site passe sur ses propres
surcharges.

Les recettes du kit PHIL UX pour le Symfony UX Toolkit tirent leur apparence de cette feuille de style.

## État

| Partie | Ce que c'est | État |
|---|---|---|
| Jetons : dimensions | Espace, texte, rayon, ombre en tailles de t-shirt, polices, mouvement, multiplicateurs globaux | Implémenté et testé |
| Jetons : palette | Sept teintes OKLCH numérotées selon la clarté, dans sRGB | Implémenté et testé |
| Jetons : rôles de couleur | Surface, texte, lien, focus ; la grille remplissage/bordure/dessus × discret/fort de six rôles ; clair et sombre | Implémenté et testé |
| Base sans classe | Typographie, éléments de la page, anneau de focus, formulaires et balisage des formulaires de Symfony, boutons, tableaux, dialogues, dévoilements, navigation, jauges, bulles, mouvement réduit | Implémenté et testé |
| Mise en page | `isStack`, `isCluster`, `isGrid`, `isCenter`, `isNarrow`, `isSplit`, `isFullHeight`, `isCover`, `isBleed`, `hasEntrance` | Implémenté |
| Composants d'affichage | `isEyebrow`, `isHeadline`, `isReveal` (le dévoilement par découpage), `isLogo`, `isProse`, `isReaction`, `isReactionPicker` | Implémenté |
| Tonalités et tailles | `isPrimary`, `isNeutral`, `isDanger`, `isSuccess`, `isWarning`, `isInfo`, `isSmall`, `isLarge` | Implémenté et testé |
| Apparences des boutons | `isButton`, `isSecondary`, `isGhost`, `isOutline`, `isLoading`, `hasIcon`, `isIconOnly`, `isButtonGroup` | Implémenté et testé |
| Composants d'interface | `isBadge`, `isAlert`, `isCard`, `isEmptyState`, `isTabs`, `isSheet`, `isSwitch`, `isTooltip`, `isDropdown`, `isAccordion`, `isBreadcrumb`, `isPagination`, `isStepper`, `isCarousel`, `isSpinner`, `isSkeleton`, `isAvatar`, `isSeparator`, `isActivityCalendar`, `isQrCode`, `isField`, `isItem`, `isToggle`, `isRadioGroup`, `isScrollArea`, `isAspectRatio`, `isCollapsible`, `isKbdGroup`, `isChart`, `isLoginForm`, `isSignupForm` | Implémenté et testé |
| Code coloré, repli d'impression | Les classes `hl-*` en rôles de couleur ; `.ujo-print-fallback` affiché sur papier seulement | Implémenté et testé |
| Recette Symfony Flex | Dans le dépôt de recettes de PHIL : associe `dist/` dans AssetMapper | Implémenté |
| Utilitaires | `isVisuallyHidden`, `isSkipLink`, `isMuted`, `isGrow` | Implémenté |
| `dist/phil-ux.css` | La construction en un seul fichier, sa fraîcheur testée | Implémenté et testé |
| `ContrastRule`, `TokenSheet`, `Oklch` | La règle de contraste, vérifiée sur les jetons du paquet et sur les surcharges d'un site | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | AssetMapper, npm, un fichier copié ; les couches en cascade. |
| [Jetons](doc/fr/tokens.md) | Les échelles, la palette, les rôles de couleur, et les jetons en JSON DTCG pour les outils de conception. |
| [Thèmes](doc/fr/themes.md) | Le clair et le sombre, `data-theme`, les surcharges d'un site. |
| [Éléments](doc/fr/elements.md) | Ce que la base sans classe habille, formulaires et balisage de Symfony compris. |
| [Mise en page](doc/fr/layout.md) | Les classes de mise en page. |
| [Composants](doc/fr/components.md) | Tonalités et tailles, boutons, chaque composant, les utilitaires. |
| [La règle de contraste](doc/fr/contrast.md) | La règle, et comment un site vérifie ses surcharges avec elle. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'un site change, et comment. |
| [Tests](doc/fr/testing.md) | Comment le paquet est testé. |
| [Référence](doc/fr/reference.md) | Chaque fichier et chaque type du paquet. |

## Démarrage rapide

```html
<html lang="en" data-theme="dark">
<link rel="stylesheet" href="/styles/phil-ux.css">

<div class="isSplit isFullHeight">
    <div class="isCover">
        <header>…</header>
        <main class="isCoverBody">
            <hgroup>
                <p class="isEyebrow">Coming soon</p>
                <h1 class="isHeadline">Studio</h1>
            </hgroup>
        </main>
    </div>
    <figure class="isBleed hasEntrance"><img src="/images/main.png" alt=""></figure>
</div>
```

## Prérequis

Un navigateur de 2024 ou plus récent, pour les couches en cascade, `light-dark()`, `oklch()` et `:user-invalid` ;
l'animation de `details` et de la couche supérieure vient avec les navigateurs qui l'ont, et le reste fonctionne sans
elle. PHP 8.5 ou plus récent pour la règle de contraste, sans extension.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, faisant affaire sous le nom Le Gnome Archiviste ; un projet Parler Haut Interagir Librement.
Voir [LICENSE](LICENSE).
