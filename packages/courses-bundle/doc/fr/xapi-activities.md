<!-- phil-dev source-sha256=b16ddb06e0583057431936860138a4d7a6179f2c61ee3869c58d1e77e28787bd -->
# Activités xAPI

Un paquet xAPI — du contenu qui rapporte à un entrepôt de traces d'apprentissage au moyen d'énoncés, sans les règles de
cmi5 — s'exécute comme une activité de type `xapi`. Il est lancé avec les paramètres de lancement que lisent les paquets
xAPI, parle au même point de terminaison xAPI que les unités cmi5, avec les mêmes jetons, et fait avancer la progression
de l'apprenant par ses propres énoncés `completed`, `passed` et `failed`. L'apprenant ne peut pas la marquer comme
terminée.

## L'activité

| Réglage | Obligatoire | Ce qu'il contient |
|---|---|---|
| `activity_id` | oui | L'IRI par laquelle le paquet nomme son activité — un schéma, un deux-points, aucune espace, 2 048 octets au plus —, l'identifiant que ses énoncés portent comme objet. |
| `url` | l'un de `url` et `path` | Une adresse absolue du contenu. |
| `path`, `query` | l'un de `url` et `path` | Le fichier de lancement dans le paquet, servi depuis l'origine du contenu, et sa requête. |
| `launch_method` | non | `AnyWindow`, la valeur par défaut, dans un cadre de la page de l'activité ; ou `OwnWindow`, ouvert dans sa propre fenêtre. |
| `mastery_score` | non | La note ramenée à l'échelle, de 0 à 1, qui réussit l'activité. |

```php
$courses->addActivity($module->id, 'Knots', 'xapi', [
    'activity_id' => 'https://example.org/xapi/knots',
    'path' => 'knots/index.html',
    'mastery_score' => 0.8,
], CompletionRequirement::Passed);
```

L'exigence est celle de l'activité, comme pour tous les types : `Completed`, `Passed`, `CompletedAndPassed` ou
`CompletedOrPassed`.

## Le lancement

Un lancement abandonne la session que l'apprenant a encore ouverte pour l'activité, en ouvre une nouvelle et ajoute à
l'URL :

| Paramètre | Valeur |
|---|---|
| `endpoint` | Le point de terminaison xAPI du bundle, la route `phil_courses_xapi`. |
| `auth` | `Basic` et le jeton de la session. |
| `actor` | L'apprenant comme agent xAPI, nommé par un compte du site : `{"objectType":"Agent","account":{"homePage":"https://www.example.org/","name":"<learner>"}}`. |
| `registration` | L'enregistrement de l'inscription, le même à chaque lancement. |
| `activity_id` | Le `activity_id` de l'activité. |

Le jeton est celui de cmi5 : l'identifiant de la session et un HMAC de celui-ci sous `phil_courses.cmi5.secret`, de sorte
que rien de secret n'est conservé. Les paquets xAPI le lisent dans l'URL plutôt que dans une URL de récupération ; la
session compte donc comme récupérée à son lancement. Le jeton n'ouvre que cette session, tant qu'elle n'est ni
abandonnée ni expirée — `phil_courses.cmi5.session_ttl` secondes, quatre heures par défaut. La page d'accueil des comptes
est `phil_courses.cmi5.actor_home_page` : sans elle, le lancement est refusé.

## Les énoncés

Le contenu envoie ses énoncés par POST ou PUT à `statements` du point de terminaison, un seul ou une liste d'au plus 50,
1 Mio au plus. Chacun est vérifié avant que l'entrepôt de traces d'apprentissage le conserve :

- l'acteur est l'apprenant de la session, nommé par le même compte — jamais un autre apprenant, un groupe ou une adresse
  de courriel ;
- le verbe est nommé par une IRI et n'est pas `voided` : un résultat enregistré demeure ;
- l'objet est une activité nommée par une IRI, ou une référence à un énoncé nommé par un UUID ;
- l'enregistrement, quand l'énoncé en nomme un, est celui de la session, et il est ajouté quand l'énoncé n'en nomme aucun ;
- l'identifiant, quand il est fourni, est un UUID ; une note ramenée à l'échelle va de -1 à 1.

Un énoncé refusé reçoit la réponse `403` avec la raison, les énoncés qui le précèdent dans la requête étant conservés ;
un entrepôt qui ne peut en conserver un répond `503`. Les énoncés sur d'autres activités du paquet — une page, une
question — sont conservés et ne font rien avancer.

Un énoncé sur l'activité lancée fait avancer la progression de l'apprenant :

| Verbe | Terminée | Réussite |
|---|---|---|
| `http://adlnet.gov/expapi/verbs/completed` | oui | `result.success` quand c'est un booléen |
| `http://adlnet.gov/expapi/verbs/passed` | quand `result.completion` est vrai | réussie |
| `http://adlnet.gov/expapi/verbs/failed` | quand `result.completion` est vrai | échouée |

Avec un `mastery_score`, une note ramenée à l'échelle décide de la réussite pour `completed` et `passed` : à égalité ou
au-dessus, l'apprenant a réussi ; en dessous, il a échoué ; `failed` reste un échec. La meilleure note est conservée,
comme pour toutes les activités.

Les documents d'état du paquet — un signet, ses données de suspension — sont conservés sous le `activity_id` de
l'activité, l'enregistrement et leur identifiant, dans l'entrepôt des documents d'état de cmi5. Le point de terminaison ne
sert ni les énoncés en retour ni les préférences de l'apprenant, comme pour cmi5.

## L'entrepôt de traces d'apprentissage

Les énoncés vont au `LearningRecordStore` de [cmi5](cmi5.md#lentrepôt-de-traces-dapprentissage) : l'entrepôt intégré au
bundle, un entrepôt distant par phil/safe-http, ou celui de l'application. Ils sont conservés sous l'enregistrement de
l'inscription, avec les énoncés cmi5 du même apprenant.

## Importer un paquet xAPI

Une archive qui contient `tincan.xml` à sa racine, et ni `cmi5.xml` ni `imsmanifest.xml`, est un paquet xAPI.
L'importation le lit par le service `XapiPackageReader` nommé dans `phil_courses.import.xapi_reader`, par défaut
`ElearningXapiPackageReader`, l'adaptateur du `XapiCourseReader` de phil/elearning-packages. Il lit `tincan.xml` dans les
limites de l'importation — sa taille, ses nœuds XML, ses activités —, prend l'IRI, le titre et la description de
l'activité du cours dans la langue de `phil_courses.import.language`, garde chaque activité lançable dans l'ordre du
paquet, et transforme les problèmes qu'il a mis de côté — une activité déclarée deux fois, un nom manquant, un type
d'interaction inconnu — en avertissements de l'importation. Un `tincan.xml` ne donne aucune note de maîtrise. Ce que la
bibliothèque refuse — aucune racine `<tincan>`, aucun `<activities>`, aucune activité lançable, un lancement qui sort du
paquet — fait refuser le paquet avec son message, sous forme de `PackageRefused`.

Le lecteur retourne un `XapiPackage` — l'IRI du cours, son titre et sa description, ses activités lançables et les notes
de la lecture —, que l'importation transforme en cours d'origine `xapi` avec un module nommé d'après le cours. Chaque
activité lançable devient une activité `xapi` qui garde son IRI et sa note de maîtrise ; celle qui a une note de
maîtrise est satisfaite une fois réussie, les autres une fois terminées. Un lancement qui n'est ni un fichier du paquet
ni une adresse absolue fait refuser le paquet. Les fichiers du paquet sont stockés et servis depuis l'origine du
contenu, comme pour SCORM et cmi5.

Une application remplace le lecteur en nommant son propre service, qui implémente le port :

```php
final readonly class AuthoringToolXapiReader implements XapiPackageReader
{
    public function read(PackageArchive $archive): XapiPackage
    {
        // Reads tincan.xml within bounds and returns the launchable activities.
    }
}
```

```yaml
phil_courses:
    import:
        xapi_reader: App\Learning\AuthoringToolXapiReader
```

## Vie privée

Les sessions de lancement et les énoncés d'une activité xAPI sont ceux de l'enregistrement de l'inscription : le module de
vie privée `courses_cmi5` les fournit dans une demande d'accès et les efface, avec les enregistrements de cmi5, comme
l'explique [cmi5](cmi5.md#vie-privée). Un entrepôt distant, ou celui de l'application, conserve ce qu'on lui a envoyé ;
l'exportation le dit.
