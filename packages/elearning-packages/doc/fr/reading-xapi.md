<!-- phil-dev source-sha256=b19c577e5886a85983b95075fa6c2e75f7ada561bf7d1c0be89553dc59f414a7 -->
# Lire les paquets xAPI

Un paquet xAPI qui n'est ni SCORM ni cmi5 décrit ses activités dans `tincan.xml`, à la racine de l'archive : chaque
activité a une IRI, un type, un nom et une description dans une ou plusieurs langues, et l'activité qu'une plateforme
lance nomme son fichier ou son adresse de lancement. Les énoncés sur ces activités vont à un Learning Record Store ; la
bibliothèque lit le paquet, et laisse le lancement et les énoncés à la plateforme.

## À partir d'un paquet

```php
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Xapi\XapiCourse;

$package = new PackageReader(language: 'fr')->readFile('/path/to/course.zip');

if ($package->manifest instanceof XapiCourse) {
    $package->format;               // PackageFormat::Xapi
    $package->identifier();         // 'https://example.org/xapi/safety', the IRI of the course activity
    $package->title();              // its name in the language asked for
    $package->manifest->activities; // every activity, in order
}
```

Un paquet est lu comme xAPI quand `tincan.xml` est à sa racine et que ni `cmi5.xml` ni `imsmanifest.xml` ne l'est : un
paquet qui contient à la fois un manifeste SCORM et `tincan.xml` est lu comme SCORM. Le fichier de lancement de chaque
activité lançable est vérifié : `The launch file "…" of the activity "…" is not in the package.` Les problèmes que le
lecteur a laissés de côté sont notés dans `$package->warnings` sous forme de phrases, et conservés typés dans
`XapiCourse::$warnings`.

## À partir de la description seule

```php
use Phil\ElearningPackages\Xapi\XapiCourseReader;

$course = new XapiCourseReader(language: 'fr-CA')->read($xml);

$course->id();           // the IRI of the course activity
$course->title;          // its name in the language asked for
$course->course;         // the course activity
$course->launchable();   // the activities a platform may launch
$course->interactions(); // the questions, of type cmi.interaction
$course->activity('https://example.org/xapi/quiz-1/q1');
```

L'activité du cours est la première activité lançable de type `http://adlnet.gov/expapi/activities/course`, sinon la
première activité lançable.

## Ce qui est lu

| Valeur | Provient de |
|---|---|
| `XapiActivity::$id` | L'attribut `id` : une IRI — un schéma, un deux-points, aucune espace blanche —, de 1 à 2 048 octets |
| `XapiActivity::$type` | L'attribut `type`, quand c'est une IRI |
| `XapiActivity::$names`, `$descriptions` | Chaque `<name>` et `<description>` selon son attribut `lang` en minuscules — `und` quand il est absent ou n'est pas une étiquette de langue —, le premier de chaque langue conservé, les textes vides laissés de côté, 32 langues au plus |
| `XapiActivity::$launch` | Le `<launch>` dans la langue demandée, sinon le premier : un fichier du paquet, résolu et vérifié, sa requête et son fragment conservés ; ou une adresse absolue `http` ou `https`, sous sa forme canonique |
| `XapiActivity::$interactionType` | `<interactionType>`, un `InteractionType`, sans égard à la casse |
| `XapiActivity::$correctResponses` | Chaque `<correctResponsePattern>` de `<correctResponsePatterns>`, tel qu'il est écrit, les 32 premiers conservés, pour une question qui a un type d'interaction |
| `XapiActivity::$components` | `<choices>`, `<scale>`, `<source>`, `<target>` et `<steps>`, chacun une liste de `<component>` avec son `<id>` — ou son attribut `id` — et sa `<description>` par langue, 100 composants au plus par liste, par leur valeur de `ComponentList` |

`name()` et `description()` choisissent un texte dans une langue : l'étiquette elle-même, sinon le premier texte de sa
langue principale — `fr` et `fr-FR` choisissent `fr-CA` —, sinon le premier écrit. Un nom a pour valeur par défaut l'IRI.

## Questions

Une activité de type `cmi.interaction` est une question. Son type d'interaction dit quelles listes de composants elle
offre, et `ComponentList::of()` les donne :

| Type d'interaction | Listes |
|---|---|
| `choice`, `sequencing` | `Choices` |
| `likert` | `Scale` |
| `matching` | `Source`, `Target` |
| `performance` | `Steps` |
| les autres | aucune |

```php
use Phil\ElearningPackages\Xapi\ComponentList;

$question = $course->activity('https://example.org/xapi/quiz-1/q1');

$question->correctResponses;                         // ['golf[,]tetris']
$question->components(ComponentList::Choices);       // InteractionComponent values, in order
$question->components(ComponentList::Choices)[0]->id;
$question->components(ComponentList::Choices)[0]->description('fr');
$question->definition();                             // the activity definition of xAPI
```

L'identifiant d'un composant compte de 1 à 255 octets, sans espace blanche : c'est par lui qu'une bonne réponse, et la
réponse d'un apprenant, nomment le composant. Chaque bonne réponse est vérifiée contre les listes que la question
déclare — les choix d'une réponse `choice` ou `sequencing` séparés par `[,]`, le point d'une réponse `likert`, la source
et la cible de chaque paire `source[.]target` d'une réponse `matching`, l'étape de chaque `step[.]answer` d'une réponse
`performance` —, les délimiteurs `{case_matters=…}`, `{order_matters=…}` et `{lang=…}` à son début mis à part. Une liste
que la question ne déclare pas n'est pas vérifiée, de sorte qu'un paquet qui donne des bonnes réponses sans composants se
lit sans note.

`definition()` donne l'activité comme un énoncé ou un entrepôt de traces d'apprentissage la porte : `name` et
`description` comme tables de langues, `type`, `interactionType`, `correctResponsesPattern` et chaque liste de
composants avec `id` et `description` ; ce que le paquet ne donne pas est omis.

## Avertissements et refus

Ce qui peut être laissé de côté l'est, sous la forme d'un `XapiWarning` avec son `XapiWarningKind`, l'IRI de son activité
et une phrase :

| Genre | Quand |
|---|---|
| `UnnamedActivity` | Une activité n'a pas de nom : son IRI en tient lieu |
| `DuplicateActivity` | Une seconde activité utilise une IRI : elle est laissée de côté |
| `InvalidActivityType` | Un type n'est pas une IRI : il est laissé de côté |
| `UnknownInteractionType` | Un type d'interaction n'est pas de ceux que xAPI définit : il est laissé de côté |
| `InteractionTypeWithoutInteraction` | Une activité nomme un type d'interaction sans être de type `cmi.interaction` : le type est conservé |
| `TooManyLanguages` | Un nom ou une description est écrit en plus de 32 langues : les autres sont laissées de côté |
| `MisplacedResponses` | Une activité sans type d'interaction déclare des bonnes réponses : elles sont laissées de côté |
| `TooManyResponses` | Une question déclare plus de 32 bonnes réponses : les autres sont laissées de côté |
| `MisplacedComponents` | Une question déclare une liste de composants que son type d'interaction n'utilise pas : la liste est laissée de côté |
| `InvalidComponent` | Un composant n'a pas d'identifiant de 1 à 255 octets sans espace blanche : il est laissé de côté |
| `DuplicateComponent` | Un second composant d'une liste utilise un identifiant : il est laissé de côté |
| `TooManyComponents` | Une liste compte plus de 100 composants : les autres sont laissés de côté |
| `UnknownResponseComponent` | Une bonne réponse nomme un composant que sa question ne déclare pas : la réponse est conservée |

Ce qui ne peut pas être laissé de côté fait refuser le paquet par `PackageRefused` : un document non sûr ou mal formé,
sans racine `<tincan>` ni `<activities>`, une activité sans IRI, plus d'activités que `PackageLimits::$items`, aucune
activité avec un `<launch>`, un fichier de lancement qui sort du paquet, un lancement d'un autre schéma — `javascript:`,
`data:` —, ou une adresse absolue que `phil/url` refuse, comme une adresse qui contient un nom d'utilisateur et un mot de
passe.

## Sécurité

`tincan.xml` est lu comme les autres manifestes : borné en taille, refusé quand il déclare un type de document ou une
entité, ses nœuds XML et leur profondeur comptés avant qu'il soit chargé, analysé sans accès au réseau. Ses activités
comptent contre `PackageLimits::$items` ; les bonnes réponses et les composants d'une question sont bornés aussi, et
chaque réponse est vérifiée en un seul passage sur ses parties. Une adresse de lancement absolue est lue par `phil/url`, comme un navigateur la
lit, puis conservée sous sa forme canonique.

## Ce que la bibliothèque laisse à son appelant

Elle ne lance pas d'activités, ne construit pas l'URL de lancement avec le point de terminaison, l'acteur et
l'enregistrement, n'émet pas d'identifiants de connexion pour le Learning Record Store, et ne reçoit ni ne vérifie
d'énoncés.
