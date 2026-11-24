<!-- phil-dev source-sha256=3b218975a8d351bb600d55c964dc47dd32ecd1065bfefd6f516d83a0529dd0a0 -->
# Configuration

```yaml
# config/packages/phil_faq.yaml
phil_faq:
    entity_manager: default        # the Doctrine entity manager of the tables
    contact_url: /contact          # the link a search without answer shows; none when null
    layout: '@PhilFaq/layout.html.twig'
    max_questions: 500             # the most questions a page shows, 1 to 5000
    vote_lifetime: 86400           # how long the vote token of a page votes, in seconds, 60 to 604800
    markup: null                   # a Phil\Markup\Markup service that reads the answers
    kit_subdirectory: null         # the subdirectory of the recipes; the Toolkit's when null
    rate_limiters:
        votes: limiter.phil_faq_votes
        searches: limiter.phil_faq_searches
    admin:
        enabled: false
        role: ROLE_ADMIN
        layout: '@PhilFaq/admin/layout.html.twig'
        locales: [en]
```

## Les options

| Option | Valeur par défaut | Ce qu'elle règle |
|---|---|---|
| `entity_manager` | `default` | Le gestionnaire d'entités dont la connexion contient les tables. |
| `contact_url` | `null` | L'adresse du formulaire de contact, affichée après une recherche sans résultat. |
| `layout` | `@PhilFaq/layout.html.twig` | Le gabarit que la page du bundle étend, avec les blocs `title` et `content`. |
| `max_questions` | `500` | Le plus grand nombre de questions qu'une page affiche, les premières dans l'ordre. |
| `vote_lifetime` | `86400` | La durée pendant laquelle le jeton de vote d'une page vote, en secondes. |
| `markup` | `null` | Le service `Phil\Markup\Markup` qui compile et purifie les réponses ; s'il est nul, le bundle en construit un avec le compilateur de `phil/markup` et ses politiques de purification par défaut. |
| `kit_subdirectory` | `null` | L'emplacement des recettes, sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` s'il est nul. |
| `rate_limiters.votes` | `limiter.phil_faq_votes` | La fabrique de limiteurs de débit des votes, par question. |
| `rate_limiters.searches` | `limiter.phil_faq_searches` | La fabrique de limiteurs de débit des recherches comptées, par langue. |
| `admin.enabled` | `false` | Enregistre l'administration ; elle exige Symfony Security. |
| `admin.role` | `ROLE_ADMIN` | Le rôle qui ouvre l'administration. |
| `admin.layout` | `@PhilFaq/admin/layout.html.twig` | Le gabarit que les pages de l'administration étendent. |
| `admin.locales` | `[en]` | Les langues que l'administration édite, la première par défaut. |

## Ce que le bundle préconfigure

Le bundle ajoute le jeton `phil_faq` à `framework.csrf_protection.stateless_token_ids`, associe `Domain\Model` à
Doctrine et donne à AssetMapper le chemin `@phil/faq-bundle` du contrôleur. Il déclare aussi deux limiteurs qu'une
application modifie en déclarant les mêmes noms dans `framework.rate_limiter` :

```yaml
framework:
    rate_limiter:
        phil_faq_votes: { policy: sliding_window, limit: 60, interval: '1 minute' }      # per question
        phil_faq_searches: { policy: sliding_window, limit: 120, interval: '1 minute' }  # per language
```

Un limiteur est indexé par une question ou une langue, jamais par un lecteur : aucune adresse n'est conservée, pas même
une minute. Une recherche au-delà de la limite affiche tout de même sa page ; seul son décompte attend.

Le conteneur s'arrête avec un message qui dit quoi changer quand `framework.csrf_protection` est désactivé.

## Les réponses

Une réponse s'écrit en Markdown, en HTML ou en texte brut (les formats de l'administration), ou dans n'importe quel
format de `phil/markup` par `FaqEditor` et l'import. Elle est compilée et purifiée une seule fois, à son enregistrement,
au niveau `forum` de `phil/markup` : paragraphes, emphase, liens, listes, citations et images, rien qui s'exécute. Sa
source et son format sont conservés à côté du HTML, de sorte qu'elle s'édite telle qu'elle a été écrite.

## Renseignements personnels

Le bundle ne conserve rien d'un lecteur. Un vote conserve un total par question et l'empreinte d'un jeton aléatoire ;
une recherche sans réponse conserve ses mots normalisés, sa langue, un total et un jour. Aucune adresse, aucun
identifiant, aucun agent utilisateur et aucun témoin n'est lu ni écrit, ce qui explique que le bundle ne déclare aucun
module à un bundle de vie privée. L'administration ne nomme personne non plus : ce qu'un éditeur change est à auditer
par l'application.

## Les services

| Service | Classe | Public |
|---|---|---|
| `Phil\FaqBundle\Application\FaqEditor` | `FaqEditor` | oui |
| `Phil\FaqBundle\Application\FaqPages` | `FaqPages` | oui |
| `Phil\FaqBundle\Application\FaqStructuredData` | `FaqStructuredData` | oui |
| `Phil\FaqBundle\Application\FaqMarkdown` | `FaqMarkdown` | oui |
| `Phil\FaqBundle\Application\FaqTransfer` | `FaqTransfer` | oui |
| `Phil\FaqBundle\Application\HelpfulVotes` | `HelpfulVotes` | oui |
| `Phil\FaqBundle\Application\UnansweredSearches` | `UnansweredSearches` | oui |
| `Phil\FaqBundle\Domain\Contract\FaqRepository` | alias de `DoctrineFaqRepository` | oui |
| `Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository` | alias de `DbalUnansweredSearches` | oui |
| `Phil\FaqBundle\Domain\Contract\SpentVoteTokenRepository` | alias de `DbalSpentVoteTokens` | oui |
| `phil_faq.markup` | `Phil\Markup\Markup`, ou un alias de `markup` | non |
| `phil_faq.throttle.votes`, `phil_faq.throttle.searches` | `SymfonyThrottle` | non |
| `Phil\FaqBundle\Infrastructure\Http\FaqController` | `FaqController` | oui |
| `Phil\FaqBundle\Infrastructure\Http\FaqAdminController` | `FaqAdminController`, avec l'administration | oui |
| `phil:faq:export`, `phil:faq:import` | `ExportCommand`, `ImportCommand`, avec la console et le composant YAML | non |
