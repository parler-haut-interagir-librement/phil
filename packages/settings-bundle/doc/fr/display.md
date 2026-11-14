<!-- phil-dev source-sha256=6821c1bd749034833da66653e0076382bfb2b399004f99a5e43aff6d94e397d9 -->
# Préférences d'affichage

## Ce que le lecteur choisit

Le bundle déclare quatre choix dans la section `display`, chacun à `system` par défaut, qui suit l'appareil :

| Réglage | Choix | Attribut de la racine | Requête média du système |
|---|---|---|---|
| `display.theme` | `system`, `light`, `dark` | `data-theme` | `prefers-color-scheme` |
| `display.motion` | `system`, `reduce` | `data-motion` | `prefers-reduced-motion` |
| `display.contrast` | `system`, `more` | `data-contrast` | `prefers-contrast` |
| `display.text_size` | `system`, `large`, `larger` | `data-text-size` | la taille du texte du navigateur |

`display: false` les retire. Tout choix qu'une application déclare avec un `html_attribute` fonctionne de la même
façon.

## Sur la page

`phil_document_attributes()` écrit sur l'élément racine les attributs des choix de l'utilisateur courant ou du
visiteur, sauf un choix à sa valeur par défaut, qui n'écrit rien :

```twig
<html lang="{{ app.request.locale }}" {{ phil_document_attributes() }}>
{# <html lang="fr" data-motion="reduce" data-text-size="large"> #}
```

Sans attribut, la feuille de style s'en tient aux requêtes média du système. `phil/ux-css` lit les quatre attributs
comme il lit les requêtes : voyez sa documentation des éléments. Leur déclaration limite les noms et les valeurs aux
lettres minuscules, aux chiffres, à `_` et à `-`. Ils sont donc écrits sans échappement.

## Pour les visiteurs

Un réglage conservé pour les visiteurs, comme les préférences d'affichage, est enregistré pour un visiteur dans le
témoin `phil_preferences` : `display.motion=reduce&display.theme=dark`, les valeurs qui diffèrent de la valeur par
défaut, triées. Le témoin ne contient que ce que le visiteur a demandé au site de retenir sur son propre affichage. Il
est donc strictement nécessaire et n'exige aucune bannière de consentement. Il dure un an, il est envoyé par HTTPS
quand la requête l'est, les scripts ne peuvent pas le lire, et il reste hors des requêtes des autres sites
(`SameSite=Lax`). Le retour à toutes les valeurs par défaut l'efface.

Le témoin est lu avec indulgence et dans des limites : au-delà de 1 024 octets, il est ignoré ; au-delà de sa 32e
paire, le reste l'est. Une clé inconnue, un réglage non conservé pour les visiteurs ou une valeur refusée est laissé de
côté. Les préférences d'affichage d'un utilisateur connecté sont enregistrées avec ses réglages et écrites aussi dans
le témoin, pour que le site garde son apparence une fois l'utilisateur déconnecté.

## Dès qu'elle est choisie

Le formulaire d'une section qui contient un tel choix porte le contrôleur Stimulus `phil--settings-bundle--document`.
Il écrit l'attribut sur l'élément racine dès qu'un bouton radio est choisi, avant que le formulaire soit enregistré.
Avec AssetMapper, le bundle le sert sans étape de construction. Il s'enregistre par `assets/controllers.json`, comme
tout paquet Symfony UX.

Sans JavaScript, le choix s'applique une fois enregistré.
