<!-- phil-dev source-sha256=a66fd805511f316c5cdd79c94e08e526882c05bb9673eaeb4a8aea3d1640bc86 -->
# Gabarits

## Le composant

```twig
<twig:PhilFaq />
```

`PhilFaq` affiche les questions de la langue de la requête, filtrées par le `q` de la requête. Ses props :

| Prop | Valeur par défaut | Ce qu'elle règle |
|---|---|---|
| `page` | `null` | Un `FaqPage` déjà lu, comme la page du bundle le passe ; lu depuis la requête s'il est nul. |
| `query` | `null` | La recherche ; le `q` de la requête s'il est nul. |
| `locale` | `null` | La langue ; celle de la requête si elle est nulle. |
| `action` | `null` | La destination du formulaire de recherche ; le chemin courant s'il est nul. |
| `headingLevel` | `2` | Le niveau des titres de section, de 2 à 5. |
| `structuredData` | `true` | Si le JSON-LD est écrit. |
| `nonce` | `null` | Le *nonce* de la politique de sécurité du contenu de la page, pour le script du JSON-LD. |

La page du bundle, `@PhilFaq/page.html.twig`, étend `phil_faq.layout` et écrit le `h1` et le composant. Une
application surcharge n'importe quel gabarit sous `templates/bundles/PhilFaqBundle/`.

## Ce qu'il affiche

- Un formulaire de recherche, `method="get"`, avec son libellé, un champ `q` et son bouton : sans JavaScript, le serveur
  filtre.
- Une région dynamique qui dit combien de questions correspondent, et un lien qui les affiche toutes de nouveau.
- Après une recherche sans résultat, la phrase qui le dit et le lien vers `contact_url`.
- Chaque section, un `section` dont l'`id` est `faq-` suivi de sa clé, sous son titre.
- Chaque question, un élément `details` dont l'`id` est son ancre, sa formulation dans le `summary`, sa réponse, un lien
  vers son ancre, un bouton qui copie le lien complet, le jour de son changement et le formulaire de vote. Une question
  affichée dans sa langue de repli porte ce `lang`.
- Le JSON-LD `FAQPage` des questions affichées.

Les questions sont ouvertes après une recherche, et celle pour laquelle un formulaire simple a voté est ouverte au
retour de la page. Avec le contrôleur, la question que nomme l'adresse s'ouvre au chargement de la page et quand
l'adresse change. Aucune question n'est jamais un accordéon sans ancre : chacune a sa propre adresse.

Les recettes sont celles du kit, sous leurs noms du Toolkit ; la structure porte les classes de la feuille de style du
kit (`isAccordion`, `isAccordionItem`, `isAccordionTrigger`, `isAccordionContent`) et des classes BEM qui lui sont
propres, `phil-faq__*`, pour les styles d'un site.

## La recherche

La recherche et les questions sont normalisées de la même façon, sur le serveur et dans le navigateur : formes de
compatibilité décomposées, accents et casse supprimés, toute suite de caractères qui ne sont ni des lettres ni des
chiffres remplacée par une seule espace ; une recherche garde ses 100 premiers caractères et ses 10 premiers mots. Une
question correspond quand sa formulation ou sa réponse contient chaque mot.

Une recherche sans résultat est comptée dès qu'elle compte trois caractères : par la page quand le formulaire atteint le
serveur, et par le contrôleur après une pause de 1,5 seconde sans frappe, ou tout de suite sur Entrée, une fois par
recherche et par page.

## Le vote

Le formulaire envoie `helpful` (`yes` ou `no`), le jeton de vote de la page, le jeton CSRF `phil_faq` et le chemin du
retour. Sans JavaScript, le serveur répond `303` vers ce chemin, avec `voted` et l'ancre, et la page remercie le
lecteur ; un chemin qui n'est pas celui du site est remplacé par la page des questions. Avec le contrôleur, le vote est
envoyé en JSON et le formulaire est remplacé par les remerciements ; une question déjà votée depuis ce navigateur
affiche les remerciements au chargement de la page.

| Réponse | État | JSON |
|---|---|---|
| Compté | `200`, ou `303` pour un formulaire simple | `{"yes": 3, "no": 1}` |
| Ni oui ni non | `422` | `{"error": "invalid"}` |
| Vérification CSRF ou jeton refusé, jeton expiré | `403` | `{"error": "refused"}` |
| Aucune question avec cette ancre | `404` | `{"error": "unknown"}` |
| Ce jeton a déjà voté pour cette question | `409` | `{"error": "already"}` |
| Trop de votes sur cette question | `429`, avec `Retry-After` | `{"error": "too_many"}` |

Un formulaire simple refusé affiche `@PhilFaq/stopped.html.twig`, avec la raison et le chemin du retour.

Le jeton de vote d'une page lui est propre, de sorte que la page du bundle est envoyée avec `Cache-Control: private`. Un
site qui met la page en cache dans un cache partagé donne à chaque lecteur le même jeton : chaque question compte alors
un vote par durée de vie du jeton.

## Le JSON-LD

`FaqStructuredData::graph()` donne le `FAQPage` d'une page (son adresse, son titre s'il est donné, sa langue, et chaque
question avec sa réponse acceptée en HTML purifié), et le composant l'écrit avec `JsonLd::script()`, qui échappe ce qui
pourrait fermer l'élément. Le JSON-LD ne contient aucun jeton. Une page sans question n'écrit rien.

## Le Markdown d'un `llms.txt`

`FaqMarkdown::export()` écrit les questions d'une page en Markdown : un titre, un titre par section, un titre par
question lié à son ancre, et le texte de chaque réponse. Une application le sert dans son `llms.txt`, ou comme page vers
laquelle ce fichier renvoie :

```php
$page = $pages->page(Locale::fromString('en'));
$markdown = $markdownExport->export($page, 'Frequently asked questions', 'https://example.org/faq/');
```

## Accessibilité

- La recherche a un libellé visible et un bouton d'envoi ; son résultat est annoncé dans une région dynamique polie.
- Chaque question est un élément natif `details` : le clavier, les lecteurs d'écran et la recherche dans la page du
  navigateur l'ouvrent.
- Le vote est un `fieldset` avec sa `legend` ; les remerciements sont un `status`, un échec une `alert`.
- Le bouton de copie reste caché tant qu'aucun script ne peut copier, et dit ce qui s'est passé dans sa propre région
  dynamique.
- Les titres de section suivent le plan de la page par `headingLevel`.
