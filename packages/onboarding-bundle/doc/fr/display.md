<!-- phil-dev source-sha256=ea5c59815cb2dc6d909288808a86537b2151c54d1d888bd4bc13e071a7d5e789 -->
# La liste de contrôle et la visite

## Dans un gabarit

Avec Symfony UX Twig Component :

```twig
<twig:PhilOnboarding:Checklist flow="author" class="sidebar" />
<twig:PhilOnboarding:Tour flow="author" />
<twig:PhilOnboarding:Tour flow="author" :autostart="false" />
```

Sans lui, la même chose avec les fonctions Twig :

```twig
{{ onboarding_checklist('author') }}
{{ onboarding_tour('author') }}
{{ onboarding_tour('author', false) }}

{% set progress = onboarding_progress('author') %}
{% if progress and progress.active %}{{ progress.percent }} %{% endif %}
```

Ni l'une ni l'autre n'affiche quoi que ce soit pour un visiteur. La liste de contrôle n'affiche plus rien une fois le
parcours rangé. La visite n'affiche plus rien une fois le parcours terminé ou rangé, ni pour un utilisateur qui a
désactivé les visites. Les autres attributs d'un composant vont sur son élément principal.

## La liste de contrôle

C'est une `<section>` nommée par le titre du parcours. Elle contient sa description, la part des étapes faites en mots
et sous forme de `<progress>`, puis les étapes dans une liste ordonnée. Chaque étape dit son état en mots aux lecteurs
d'écran ; l'étape courante porte `aria-current="step"` ; une étape en attente qui a une route y mène par un lien. Une
étape cochée à la main a un formulaire pour la marquer faite, une étape qui peut être passée un formulaire pour la
passer, une étape à question un formulaire de ses choix — des boutons radio, ou des cases à cocher quand plusieurs
peuvent être retenus, groupés dans un `<fieldset>` dont la légende est le titre de l'étape — et un bouton qui y répond.
Chaque bouton nomme son étape aux lecteurs d'écran. Un formulaire range la liste ; une fois toutes les étapes
faites, un autre recommence le parcours.

Les formulaires envoient leurs données aux points de terminaison avec le jeton CSRF et reviennent à la page : la liste
de contrôle n'a besoin d'aucun script.

## La visite

La visite est une boîte de dialogue non modale qui montre une étape à la fois — sa progression, son titre, sa
description, un lien vers sa route, le formulaire de sa question — pendant que la cible de l'étape est mise en évidence
et défilée jusqu'à la vue. Elle commence à la première étape en attente au chargement de la page, sauf si `autostart`
vaut false, et chaque fois que la fenêtre reçoit l'événement `phil-onboarding:start` :

```js
// in a Stimulus controller of the application, behind a "Take the tour" button
this.dispatch('start', { prefix: 'phil-onboarding', target: window });
```

| Commande | Effet |
|---|---|
| Suivant, ou la flèche droite | Marque faite une étape en attente cochée à la main, puis montre l'étape suivante ; se termine sur la dernière. Une question reste à répondre, par son formulaire. |
| Précédent, ou la flèche gauche | Montre l'étape précédente. |
| Passer cette étape | Marque l'étape passée, si elle peut l'être, puis continue. |
| Terminer la visite (×) | Range le parcours pour de bon. |
| Échap | Ferme la visite pour cette page seulement. |

Les flèches n'agissent que si le focus n'est pas dans un champ. Le panneau prend le focus quand une étape s'affiche, et
le focus revient où il était quand la visite se ferme. Un statut annonce chaque étape aux lecteurs d'écran
(« Étape 2 sur 4 : … »). Une étape dont la cible n'est pas sur la page s'affiche sans mise en évidence ; un sélecteur
que le navigateur refuse est ignoré.

La cible défile jusqu'à la vue d'un coup quand le lecteur demande un mouvement réduit, par le
`prefers-reduced-motion` du système ou par le `data-motion="reduce"` que `phil/settings-bundle` écrit sur l'élément
racine, et en douceur sinon. La feuille de style retire la transition de la mise en évidence de la même façon. Chaque
changement est envoyé aux points de terminaison avec le jeton dans l'en-tête `X-Phil-Onboarding-Token`. Un envoi qui
échoue répartit `phil--onboarding-bundle--tour:failed`, et la visite continue. Le contrôleur répartit aussi `shown`,
avec le parcours et l'étape, `closed` et `finished`.

Sans JavaScript, la visite ne s'affiche pas : la liste de contrôle permet alors de suivre le parcours.

## L'apparence

Les boutons sont la recette `Button` du kit, installée dans l'application par le Symfony UX Toolkit — voyez
[Installation](installation.md#la-recette-du-kit) —, qui les habille : `secondary` pour cocher une étape, `ghost` pour
la passer, ranger la liste ou la recommencer, `default` pour répondre et continuer dans la visite, tous de taille `sm`,
et un bouton `icon-sm` pour terminer la visite. Un gabarit l'appelle par `{% component (phil_onboarding_kit('Button')) %}`,
la fonction donnant le nom qu'elle porte sous le sous-répertoire des composants du Toolkit. La feuille de style du
bundle, `phil-onboarding/onboarding.css`, met en page la liste de contrôle, les questions et le panneau, cache les
textes destinés aux lecteurs d'écran (`phil-onboarding-visually-hidden`) et dessine la mise en évidence
(`phil-onboarding-highlight`), avec `Highlight` en couleurs forcées. Ses couleurs sont des propriétés personnalisées
qu'une application peut redéfinir :

```css
:root {
    --phil-onboarding-accent: #2557a7;
    --phil-onboarding-surface: #fff;
    --phil-onboarding-text: #1b1b1b;
    --phil-onboarding-muted: #595959;
    --phil-onboarding-radius: 0.5rem;
}
```

Les gabarits sont `@PhilOnboarding/_checklist.html.twig` et `@PhilOnboarding/_tour.html.twig`. Une application les
redéfinit comme ceux de tout bundle, sous `templates/bundles/PhilOnboardingBundle/`.

## Désactiver les visites

Avec `phil/settings-bundle`, le bundle déclare le réglage booléen `onboarding.tours`, actif par défaut, dans la section
`general` des préférences. Un utilisateur qui le désactive ne voit aucune visite et garde la liste de contrôle. Une
autre règle — un rôle, un forfait — est un `TourPreference` de l'application : voyez
[Points d'extension](extension-points.md).
