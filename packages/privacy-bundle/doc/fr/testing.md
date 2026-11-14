<!-- phil-dev source-sha256=0265c40c16aa592238885e8b4edde466ba2d8083df8cffabee73cbaff68b3d72 -->
# Tests

## Comment le bundle est testé

Les tests unitaires vivent sous `tests/Unit/`, espace de noms `Phil\PrivacyBundle\Tests\`, écrits pour PHPUnit 13 avec
`#[CoversClass]` sur chaque classe de test. Aucun ne démarre de noyau ; seuls les tests de base de données touchent des
fichiers, dans un répertoire à eux sous le répertoire temporaire du système, retiré après chaque test :

| Domaine | Tests |
|---|---|
| `Domain\Attribute`, `Domain\Declaration` | la validation de l'attribut ; les déclarations privées des parents lues d'abord ; la couverture avec et sans calendrier (fixtures `BaseModel`, `Member`) |
| `Domain\Subject` | la validation de l'identifiant, de la catégorie et du nom de module ; les invariants du rapport d'effacement |
| `Domain\Redaction` | la normalisation et les motifs de la liste de refus ; les chaînes de requête, les informations d'utilisateur, les chemins à jeton ; les options longues et courtes ; le parcours du contexte, les exceptions ; le texte de remplacement sur un motif qui échoue |
| `Domain\Retention` | les dates limites, la fin de mois ramenée, les jours bissextiles, l'UTC ; les contradictions des règles ; les ordres du calendrier et les tâches sans date limite ; ce qu'une règle déclare pour le registre, et ses limites |
| `Domain\Cookie` | l'inventaire lu à partir de sa correspondance, chaque refus, les familles de noms, les groupes par catégorie |
| `Domain\GuestRequest`, `Domain\Incident` | un secret dont seule l'empreinte est gardée, l'expiration ; les avis qu'exige un risque sérieux, chaque date refusée avant la découverte |
| `Application` | l'ordre des modules, l'isolement des échecs et les rapports mal attribués dans `SubjectErasure` ; les sections triées dans `SubjectExport` ; la vérification des purgeurs avant de purger dans `RetentionRun` ; les activités et les lacunes du registre (fixtures `FixedExporter`, `RecordingEraser`, `RecordingPurger`, `FixedScheduleSource`) |
| `Application\Export` | l'archive ouverte par l'extension zip (fixture `Unzip`), ses en-têtes octet par octet, les fichiers compressés et stockés, chaque nom dangereux refusé, le README |
| `Application\GuestRequest` | le lien envoyé et ouvert une fois, l'accès et l'effacement, un effacement incomplet gardé, l'expiration, le verrou, les deux limites, rien sur une personne dans le stockage du limiteur (fixtures `MemoryGuestRequests`, `RecordingLinkSender`) |
| `Infrastructure\Monolog` | le message, le contexte et `extra` caviardés sur un vrai `LogRecord` |
| `Domain\Anonymization` | chaque anonymiseur intégré sur une source aléatoire amorcée et un sel fixe, pour que chaque valeur soit connue ; les chiffres de contrôle IBAN de chaque forme de pays ; les décalages d'une date ; les paquets de locale, leurs données vérifiées par empreinte et leurs motifs téléphoniques comparés aux plages fictives ; les options, les règles, les plans et les déclarations à travers les parents (fixture `Customer`) |
| `Application\Anonymization` | l'exécution sur des tables en mémoire (fixture `MemoryTarget`) : une transaction, les nuls laissés, une amorce répétée, un sel tiré par exécution, chaque refus avant qu'une ligne soit lue, l'annulation sur une mauvaise option, les clés de plusieurs colonnes ; la garde |
| `Infrastructure\Database` | l'identité de la connexion de chaque plateforme, sans se connecter ; la cible DBAL par pages, par clé, sur des noms entre guillemets ; les demandes sans compte, leur adresse scellée, et le registre des incidents sur SQLite en mémoire |
| `Infrastructure\Doctrine` | le plan tiré d'un mappage ORM sur SQLite en mémoire : les attributs, les catégories, la configuration, l'héritage à table unique, une superclasse mappée, un objet intégré |
| `Infrastructure\Console` | la commande d'anonymisation sur un fichier SQLite : l'essai à blanc, la garde, `--confirm`, `--seed`, `--strict` ; le registre et ses lacunes ; l'inscription, le complément et la liste des incidents |
| `Infrastructure\Twig`, `Infrastructure\Mailer`, `Infrastructure\Http` | ce que reçoit l'espace réservé d'un cadre bloqué et chaque option refusée ; chaque forme d'une adresse YouTube et Vimeo changée en son lecteur par `phil/url`, les adresses gardées telles quelles et celles refusées ; le lien envoyé tout de suite ; les témoins non déclarés d'une réponse et l'avertissement |
| `PhilPrivacyBundle` | l'extension chargée sur un `ContainerBuilder` non compilé : l'étiquette, la fabrique, les arguments, chaque erreur à la construction ; un conteneur compilé avec un anonymiseur et un paquet de locale de l'application, et sans connexion |

### Tests fonctionnels

`tests/Functional/` démarre un vrai noyau (`App\TestKernel` : FrameworkBundle, MonologBundle avec un gestionnaire `test`
en mémoire, et le bundle) et vérifie que ce qui atteint le gestionnaire est caviardé : un enregistrement de
l'application, une exception dans son contexte, l'URI que journalise le routeur pour `/p/{token}?…`, et un message de
404. D'autres noyaux prouvent que chaque sorte de configuration mal formée, ou une clé inconnue, arrête le noyau.
Lancez-les avec `vendor/bin/phpunit packages/privacy-bundle/tests/Functional` ; installé seul, où FrameworkBundle n'est
qu'une dépendance de développement et où Symfony ne branche aucun distributeur d'événements, ils sont ignorés.

`App\RightsKernel` ajoute un pare-feu, Twig, Messenger et SQLite en mémoire, pour les droits d'un membre de bout en bout
: la bannière, une décision avec et sans JavaScript, le Global Privacy Control, le mode sudo sur la page d'effacement,
l'annulation et l'export en archive. `CookiesEmbedsAndGuestsTest` le démarre avec l'inventaire des témoins de
`rights/config/privacy/cookies.yaml` : la politique des témoins, la bannière, un cadre bloqué puis accordé, l'aide de
test sur un témoin déclaré et sur un témoin non déclaré, et les demandes sans compte du formulaire jusqu'à l'archive et
à l'effacement, avec leurs protections. Les contrôleurs Stimulus s'exécutent sous l'exécuteur de tests de Node : `node
--import ./tests/js/register.mjs --test tests/js`.

### Depuis la racine d'un monodépôt qui installe le bundle par chemin

```bash
vendor/bin/phpunit packages/privacy-bundle/tests
```

Les tests s'exécutent sur le `vendor/` de la racine et la configuration PHPUnit de la racine.

### Installé seul

```bash
cd privacy-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

Le paquet ne livre pas de `phpunit.xml` ; `--no-configuration` lance les tests avec les valeurs par défaut de PHPUnit et
le chargeur automatique propre au paquet (`autoload-dev` associe `Phil\PrivacyBundle\Tests\` à `tests/`).

Le paquet livre aussi `composer-dependency-analyser.php` : lancez
[`shipmonk/composer-dependency-analyser`](https://github.com/shipmonk-rnd/composer-dependency-analyser) dans le paquet
installé pour vérifier que chaque paquet importé est déclaré et que chaque paquet déclaré est importé, sans exception.

## Bancs d'essai

Les bancs phpbench de `tests/Benchmark` mesurent le chemin du caviardage des journaux : le processeur Monolog sur des
enregistrements de trois tailles, la recherche dans la liste de refus, et le caviardeur de ligne de commande sur des
lignes jusqu'à 20 Ko ; et le lecteur du témoin de consentement, qu'exécute chaque requête. Lancez-les depuis la racine
du monodépôt :

```bash
composer bench -- packages/privacy-bundle/tests/Benchmark
```

Les tests de base de données demandent l'extension `pdo_sqlite` ; les identités des connexions MySQL, MariaDB et
PostgreSQL se lisent sans serveur.

## Tester vos adaptateurs

### Exportateurs et effaceurs

Testez chaque adaptateur contre son vrai stockage, et vérifiez les invariants du port :

```php
public function testErasureIsIdempotent(): void
{
    $eraser = new BillingEraser($this->connection);
    $subject = DataSubject::fromString('customer-1');

    $first = $eraser->erase($subject);
    $second = $eraser->erase($subject);

    self::assertSame('billing', $first->module->value);
    self::assertEquals($first->effects, $second->effects);
}
```

Construisez ensuite `SubjectExport` et `SubjectErasure` depuis le conteneur une fois, pour qu'un nom de module en double
fasse échouer un test plutôt qu'une requête.

### Purgeurs et calendrier

- Chargez votre vrai calendrier par votre `RetentionScheduleSource` dans un test : les erreurs de construction y
  remontent.
- Vérifiez que `RetentionRun` construit depuis le conteneur s'exécute à un instant fixe sans `MisconfiguredPrivacy`, ce
  qui prouve que chaque ligne de tâche a un purgeur.
- Vérifiez que `jobsWithoutCutoff()` est la liste que vous attendez, pour qu'un nouveau blanc soit un changement de test
  et non une surprise.
- Pour un purgeur, insérez des lignes des deux côtés d'une date limite et vérifiez que seules les plus anciennes
  partent.

### Déclarations

Écrivez le test d'inventaire montré dans [personal-data-declaration.md](personal-data-declaration.md), et lancez
`bin/console phil:privacy:register --strict` dans l'intégration continue, pour qu'un stockage sans finalité ou sans lieu
la fasse échouer.

### Témoins

Utilisez `AssertsDeclaredCookies` dans les tests fonctionnels de vos pages
([cookie-inventory.md](cookie-inventory.md#un-test-qui-échoue-sur-un-témoin-non-déclaré)) : un témoin ajouté sans sa
ligne dans l'inventaire fait échouer le test qui le visite.

### Caviardage des journaux

Vérifiez votre configuration autant que le bundle : faites passer un enregistrement par le processeur qu'a construit
le conteneur.

```php
final class LogRedactionTest extends KernelTestCase
{
    public function testTheConfiguredDenylistRedactsOurKeys(): void
    {
        $processor = self::getContainer()->get(RedactingProcessor::class); // public in the test container

        $record = $processor(new LogRecord(new DateTimeImmutable(), 'app', Level::Info, 'GET /p/abc?x=1', ['refresh_token' => 't']));

        self::assertSame('GET /p/[redacted]?[redacted]', $record->message);
        self::assertSame(['refresh_token' => '[redacted]'], $record->context);
    }
}
```

Le conteneur de test expose les services privés qui sont référencés quelque part ; si le vôtre retire le processeur
comme inutilisé, construisez-en un avec `RedactingProcessor::fromConfiguration()` à partir des mêmes listes.

Pour les options courtes, une protection utile est un test qui liste les raccourcis de vos commandes de console et
échoue tant que l'un d'eux manque à `short_options`.

### Anonymiseurs et plan

Donnez à un anonymiseur un contexte sur une source aléatoire amorcée et un sel fixe : le même appel donne la même valeur
à chaque exécution, qu'un test peut alors fixer.

```php
$context = new AnonymizationContext(
    new AnonymizerOptions(['prefix' => 'CA']),
    LocalePacks::builtIn()->get('fr_CA'),
    new Randomizer(new Xoshiro256StarStar(hash('sha256', '1', true))),
    'salt',
);

self::assertMatchesRegularExpression('/^CA\d{9}$/', new VatNumberAnonymizer()->anonymize('CA123456789', $context));
```

Pour vérifier qu'aucun champ personnel n'est laissé en clair, lancez `bin/console phil:privacy:anonymize --dry-run
--strict` dans l'intégration continue de l'application, ou vérifiez que `AnonymizationPlanReader::plan()->uncovered` est
vide.
