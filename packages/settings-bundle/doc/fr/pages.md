<!-- phil-dev source-sha256=844b960641eef277cbad82aab30d418d4a94f3f5136feaef9f0b00803871b06a -->
# Les pages de réglages

## Les préférences et les réglages du site

Le bundle a deux pages, une section à la fois. Chacune est un formulaire produit à partir des réglages de la section :

| Route | Affiche | À |
|---|---|---|
| `phil_settings_preferences` | Les réglages d'utilisateur de la section | L'utilisateur courant ; à un visiteur, les réglages conservés pour les visiteurs |
| `phil_settings_site` | Les réglages du site de la section | Les utilisateurs du rôle de chaque réglage, `site_role` par défaut |

Sans section, une page redirige vers sa première. Une section qu'elle n'affiche pas donne une `404`. Une page qui n'a
rien à afficher donne un accès refusé, qui envoie un visiteur à la page de connexion. Un formulaire valide enregistre,
puis redirige vers sa section avec `?saved=1`, pour qu'un rechargement n'enregistre rien deux fois. Une valeur refusée
affiche de nouveau le formulaire avec son erreur, dans une `422`. Les pages sont envoyées avec
`Cache-Control: no-store, private`.

## L'aperçu

À côté de son bouton d'enregistrement, le formulaire a un bouton d'aperçu, nommé `phil_settings_preview`. Un formulaire
valide soumis par lui n'enregistre rien et ne pose aucun témoin : la page revient, dans une `200`, avec le formulaire tel
qu'il a été soumis et, pour chaque réglage de la section, sa valeur actuelle et celle que lui donnerait l'enregistrement,
les réglages qui changeraient étant marqués. Les valeurs se lisent comme les gens les choisissent — un choix par son
libellé, un booléen par oui ou non, un texte ou une liste vide dite comme telle. Une valeur refusée affiche son erreur
comme le ferait un enregistrement, dans une `422`. Enregistrer, c'est alors appuyer sur le bouton d'enregistrement du
même formulaire.

`Settings::preview()` donne la même comparaison à une page de l'application, sous forme de liste de `SettingPreview`.

## Le formulaire

Chaque réglage est un champ nommé d'après sa clé, les points remplacés par des traits d'union, et libellé par ses clés
de traduction : une case à cocher, un champ numérique avec ses bornes, un champ texte avec sa longueur, des boutons
radio ou des cases à cocher. Les champs ne portent aucune classe propre : la feuille de style sans classes de
`phil/ux-css` les habille. Une valeur refusée est une erreur sur son champ, écrite dans la langue du lecteur à partir
du domaine `phil_settings`, en anglais et en français.

Le formulaire se nomme `phil_settings`, ce qui est l'identifiant de son jeton CSRF. Le bundle l'ajoute aux jetons sans
état de FrameworkBundle : la protection vérifie l'origine de la requête et n'a besoin d'aucune session. Un visiteur
peut donc enregistrer ses préférences d'affichage sans session. Le conteneur refuse de se construire quand les
formulaires sont utilisés sans leur protection CSRF.

## Le gabarit

`@PhilSettings/settings.html.twig` est une page autonome. Une application fournit le sien dans `template` : il étend
sa mise en page et inclut la partie dont le bundle produit le rendu :

```twig
{# templates/settings.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ ('phil_settings.title.' ~ scope)|trans({}, 'phil_settings') }}{% endblock %}

{% block body %}
    {{ include('@PhilSettings/_settings.html.twig') }}
{% endblock %}
```

| Variable | Contient |
|---|---|
| `form` | La vue du formulaire |
| `sections` | Chaque section affichée : `key`, `label`, `translation_domain`, `url`, `current` |
| `section` | La `SectionDefinition` courante |
| `scope` | `user` ou `site` |
| `saved` | Si le dernier changement a été enregistré |
| `preview` | Les réglages de la section avant un enregistrement, vide sauf aperçu : `key`, `label`, `translation_domain`, `current`, `proposed` et `changed`, une valeur étant des parties, chacune un `message` avec son `domain` de traduction, ou un texte quand le domaine est nul |

La partie affiche les sections dans un `nav` dont le lien courant porte `aria-current="page"`, puis le titre et l'aide
de la section, l'état une fois enregistré, l'aperçu sous forme de tableau, et le formulaire avec ses deux boutons.
