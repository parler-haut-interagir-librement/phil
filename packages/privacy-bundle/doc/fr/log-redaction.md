<!-- phil-dev source-sha256=8a93f55b31ff4f2a198962eb82102957fe93bf2d8686b9f068eeb01fdb201947 -->
# Caviardage des journaux

**État : implémenté, testé et branché depuis la configuration**, comme `RedactingProcessor`, étiqueté
`monolog.processor`.

Les journaux sont caviardés **avant** d'être écrits, pas après : un secret qui atteint un fichier, un expéditeur de
journaux ou un traqueur d'erreurs ne peut pas être repris. `RedactingProcessor` fait passer le message, le contexte et
les champs `extra` de chaque enregistrement Monolog par `ContextRedactor`, qui combine trois caviardeurs sur une même
`Denylist`.

```text
LogRecord ─► RedactingProcessor ─► ContextRedactor ─┬─ Denylist            (keys)
                                                     ├─ UrlRedactor         (every string)
                                                     └─ CommandLineRedactor (every string)
```

Le texte de remplacement est toujours `[redacted]` (`UrlRedactor::PLACEHOLDER`, exposé aussi comme
`ContextRedactor::PLACEHOLDER`).

## Ce qui est caviardé

| Entrée | Résultat | Règle |
|---|---|---|
| `['password' => 'hunter2']` | `['password' => '[redacted]']` | Clé refusée, de tout type, à toute profondeur |
| `['email' => 'ada@example.com']` | `['email' => '[redacted]']` | Une catégorie de `#[PersonalData]`, refusée par défaut |
| `['token' => ['a' => 1]]` | `['token' => '[redacted]']` | Un tableau refusé n'est pas parcouru |
| `/reset?token=abc&lang=fr` | `/reset?[redacted]` | Chaque chaîne de requête, noms de paramètres compris |
| `what?now` | `what?[redacted]` | Tout `?` suivi de caractères autres que des espaces |
| `redis://app:secret@valkey:6379` | `redis://[redacted]@valkey:6379` | Les informations d'utilisateur de chaque adresse absolue |
| `/p/abc123/edit` (préfixe `/p/` déclaré) | `/p/[redacted]/edit` | Le segment qui suit un préfixe de chemin à jeton déclaré |
| `app:import --password=hunter2` | `app:import --password=[redacted]` | La valeur d'une option longue refusée |
| `app:import -p hunter2` (`p: password` déclaré) | `app:import -p [redacted]` | La valeur d'une option courte déclarée |
| un `Throwable` sous `exception` | un tableau : classe, message caviardé, code, fichier, ligne, précédent | Pile d'appels abandonnée |
| tout autre objet, à toute profondeur | son nom de classe, `App\Message\SignUp` | Ses propriétés n'atteignent jamais le formateur |
| un cas d'énumération | sa classe et son cas, `App\Status::Open` | |
| `#frag` dans une adresse | inchangé | Les navigateurs n'envoient pas les fragments |

## `Denylist` — les clés

`Denylist::of(string ...$keys)` ou `Denylist::fromList(array $keys)` ; `denies(string $key): bool` ; `keys():
list<string>` rend les clés et les motifs normalisés, triés (utile pour vérifier la configuration dans un test).

- **Comparées par leur forme normalisée, exactement.** La casse, `_`, `-`, `.` et les espaces sont retirés des deux
  côtés : `totp_secret`, `totpSecret` et `TOTP-Secret` sont une seule clé, et un en-tête HTTP correspond quelle que soit
  sa casse.
- **Pas une recherche de sous-chaîne.** `code` ne refuse ni `postal_code` ni `country_code` : une liste qui caviarde
  tout cesse d'être lue.
- **Les motifs sont explicites et facultatifs.** `seed*` (commence par), `*seed` (finit par), `*seed*` (contient). La
  racine est normalisée : `*_seed` et `*seed` sont un seul motif. Une étoile ailleurs est refusée : un motif est un
  élargissement délibéré, écrit là où on peut le lire.
- Seules les clés **chaînes** sont comparées. Les clés entières d'une liste ne sont jamais refusées ; leurs valeurs sont
  parcourues.

La liste appartient à l'application ; le bundle décide seulement de la façon de comparer une clé.

### Les catégories de renseignements personnels

`log_redaction.personal_data_categories` ajoute à la liste de refus les noms de catégories de `#[PersonalData]`. Par
défaut, elle contient les catégories que déclarent les paquets PHIL, `RedactingProcessor::PERSONAL_DATA_CATEGORIES` :
`email`, `person_name`, `nickname`, `phone`, `ip` et `contact`. Un contexte qui journalise une adresse sous `email`, un
nom sous `personName` ou un numéro sous `Phone` journalise `[redacted]`, même avec une `denylist` vide. Chaque entrée est
une catégorie, un jeton `snake_case` en minuscules, comparée comme une clé : `email` refuse `email` et `E-mail`, pas
`email_count`. Une application y inscrit ses propres catégories, à côté de celles-ci, et `personal_data_categories: []`
désactive la règle.

## `UrlRedactor` — les chaînes

`new UrlRedactor(list<string> $tokenPathPrefixes = [])` ; `redact(string $text): string`. Appliqué au message et à
chaque valeur chaîne à toute profondeur, parce qu'une adresse journalisée sous `uri`, `referer` ou `target` porte sa
chaîne de requête de la même façon.

- **Les chaînes de requête, entières.** Les noms de paramètres partent avec les valeurs, parce qu'un nom peut être le
  secret (`?abc123`) et qu'une liste de paramètres « sûrs » est une liste que quelqu'un oublie de tenir à jour. La
  correspondance va de `?` jusqu'à un espace, `#`, un guillemet, `<` ou `>`.
- **Les informations d'utilisateur, entières.** De `scheme://` jusqu'au dernier `@` avant la fin de l'hôte : un mot de
  passe qui contient un `@` est pris en entier. Le nom d'utilisateur part aussi : un jeton passé comme nom d'utilisateur
  est un identifiant de connexion. Les clients de base de données, de cache et de courriel citent leur DSN dans les
  messages d'exception.
- **Les segments de chemin à jeton**, seulement après un préfixe **déclaré** (voir
  [configuration.md](configuration.md)). Un jeton dans le chemin survit au caviardage de la chaîne de requête ; les
  routes qui en portent un sont déclarées plutôt que devinées. Chaque préfixe déclaré est reconnu par un seul motif : le
  nombre de préfixes ne multiplie pas les passages sur une chaîne. Quand un préfixe déclaré en commence un autre (`/p/`
  et `/p/x/`), le jeton de chacun part : `/p/x/abc` devient `/p/[redacted]/[redacted]`, quel que soit l'ordre de leur
  déclaration.
- **Une chaîne de requête brute sans son `?`** (`token=abc&x=1`, ce que rend `Request::getQueryString()`) n'est pas
  reconnaissable. Mettez dans la liste de refus la clé sous laquelle elle est journalisée (`query_string`, `query`).

## `CommandLineRedactor` — les lignes de commande dans les chaînes

`new CommandLineRedactor(Denylist $denylist, array $shortOptions = [])` ; `redact(string $text): string`. Symfony
journalise une commande en échec avec sa ligne de commande dans le message et sous `command`, une clé qui n'est pas
refusée : un secret passé en option atteindrait sinon le journal deux fois.

**Les options longues.** Le nom de l'option est lu comme une clé par la liste de refus, motifs compris : `--db-password`
tombe sous `*password*`, `--api-token` sous `*token*`.

- `--name=value` : la valeur est caviardée quelle qu'elle soit, vide comprise.
- `--name value` : le mot suivant est caviardé sauf s'il commence par `--` (c'est alors une autre option, ou le `--` qui
  termine les options). Un mot qui commence par un seul `-` **est** caviardé : un secret généré peut commencer par un
  tiret.
- Une valeur est un mot de l'interpréteur : une chaîne `"…"`, ou une suite de caractères simples, de chaînes `'…'` et
  d'échappements `\` ; `'it'\''s'` est donc une seule valeur. Une suite simple s'arrête à un espace et à `"` (là où un
  message de journal qui cite la ligne de commande la referme). Un guillemet non fermé va jusqu'à la fin du texte. Une
  valeur est lue par suites et jamais rendue : une valeur de toute longueur, un mégaoctet compris, est caviardée comme
  une courte.
- Le nom doit commencer une option : `--` au début du texte, ou après un espace, un guillemet, `(` ou `=`.
- La valeur d'une option qui n'est pas refusée est gardée, et relue pour une option qu'elle pourrait citer
  (`--message "--password=x"`).

**Les options courtes, par une table déclarée seulement.** Une lettre ne dit rien d'elle-même — `-p` est `--password`
pour une commande et `--port` pour la suivante. Avec `['p' => 'password']` et `password` refusé, `-p secret`,
`-psecret`, `-p=secret` et `-vp secret` (la lettre qui ferme un groupe d'indicateurs) perdent tous leur valeur. Les
lettres respectent la casse. Sans table, ce qui est le défaut, aucune option courte n'est lue. `re-print` n'est pas lu
comme `-p`.

**Pèche par excès** : `--token --force` garde `--force`, mais `--token release` perd `release` même si `--token` était
un indicateur, et `--token -v` perd `-v`.

## `ContextRedactor` — la structure

`new ContextRedactor(Denylist $denylist, UrlRedactor $urls, array $shortOptions = [])`. Il construit son propre
`CommandLineRedactor` à partir de la liste de refus et de la table des options courtes.

| Méthode | Effet |
|---|---|
| `redactMessage(string $message): string` | Caviardage des adresses puis caviardage de la ligne de commande |
| `redactContext(array $context): array` | Parcourt le tableau : clés chaînes refusées → texte de remplacement ; chaînes → les deux caviardeurs ; tableaux → parcourus ; `Throwable` → tableau caviardé ; un cas d'énumération → `Class::Case` ; tout autre objet → son nom de classe ; les autres scalaires et null inchangés |

Un `Throwable` devient :

```php
[
    'class' => RuntimeException::class,
    'message' => 'failed GET /x?[redacted]',   // through both redactors
    'code' => 3,
    'file' => '/app/src/Client.php',
    'line' => 42,
    'previous' => [/* the same shape */] ?? null,
]
```

Le message d'exception est l'endroit où atterrit le plus souvent l'adresse d'une requête en échec. La pile d'appels est
abandonnée parce que ses arguments ne sont pas atteignables sans sérialisation. Tout autre objet devient son
nom de classe : un formateur écrirait autrement ses propriétés publiques, ou ce qu'il devient sérialisé, et un message ou
un DTO d'un autre bundle atteindrait le journal en entier, renseignements personnels compris. Un contexte qui a besoin
d'une valeur journalise cette valeur, sous une clé que la liste de refus peut juger.

## En cas d'échec

Si une expression régulière échoue (par exemple une limite de PCRE abaissée par la configuration d'exécution), le
caviardeur rend `[redacted]` pour la chaîne entière plutôt que le texte non caviardé. Une valeur longue ne fait pas, à
elle seule, échouer les motifs de la ligne de commande : ils lisent une valeur par suites, sans retour arrière, quelle
que soit sa longueur.

## `RedactingProcessor`

`Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor` implémente le `ProcessorInterface` de Monolog.

| Membre | |
|---|---|
| `__construct(ContextRedactor $redactor)` | Construire à partir de votre propre caviardeur |
| `static fromConfiguration(array $denylist, array $tokenPathPrefixes, array $shortOptions = [], array $personalDataCategories = []): self` | Construire à partir de listes simples, comme le fait le bundle, les catégories refusées comme clés ; lève `InvalidPrivacyValue` sur une entrée mal formée |
| `PERSONAL_DATA_CATEGORIES` | Les catégories que le bundle refuse par défaut |
| `__invoke(LogRecord $record): LogRecord` | Rend `$record->with(message:, context:, extra:)`, les trois caviardés ; canal, niveau et date inchangés |

`extra` est caviardé parce que d'autres processeurs le remplissent — un processeur web ajoute l'URI de la requête avec
sa chaîne de requête.

### L'ordre des processeurs dans une application Symfony

Le bundle étiquette le service `monolog.processor` sans attributs : MonologBundle l'ajoute à **chaque journal de
canal**, à la priorité 0. Gardez en tête la façon dont Monolog ordonne les processeurs :

- **Les processeurs du journal s'exécutent avant les gestionnaires**, et ceux ajoutés à un gestionnaire s'exécutent dans
  ce gestionnaire, après eux. Un processeur de gestionnaire (comme l'interpolation des messages PSR-3 que MonologBundle
  configure sur un gestionnaire) voit donc le contexte déjà caviardé — mais tout ce qu'un processeur de gestionnaire
  *ajoute* n'est pas caviardé.
- **Parmi les processeurs du journal, la priorité la plus haute passe d'abord.** Un processeur qui remplit `extra` doit
  passer avant le caviardeur ; si le vôtre est aussi à la priorité 0 ou plus bas, abaissez la priorité du caviardeur
  depuis une passe de compilation de votre noyau, enregistrée pour passer avant celle de MonologBundle :

```php
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// in Kernel::build(ContainerBuilder $container)
$container->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition(RedactingProcessor::class)
            ->clearTag('monolog.processor')
            ->addTag('monolog.processor', ['priority' => -100]);
    }
}, PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
```

### Sans MonologBundle

```php
use Monolog\Logger;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;

$logger = new Logger('app');
$logger->pushHandler($handler);
// Monolog runs the most recently pushed processor first: push the redactor first, then the processors
// that fill `extra`, so that the redactor runs after them.
$logger->pushProcessor(RedactingProcessor::fromConfiguration(['*password*', '*token*'], ['/p/']));
$logger->pushProcessor($webProcessor);
```

## Ce que le processeur ne couvre pas

- Le contenu d'un objet du contexte : seul son nom de classe est gardé.
- Le texte écrit hors de Monolog : les journaux d'accès du serveur web, les traqueurs d'erreurs qui ont leur propre
  nettoyage, `var_dump`.
- Les secrets qui ne sont ni sous une clé refusée, ni dans les identifiants ou la chaîne de requête d'une adresse, ni la
  valeur d'une option refusée : un texte libre comme `"password is hunter2"` dans un message n'est pas reconnu.
