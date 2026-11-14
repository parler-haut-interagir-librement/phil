<!-- phil-dev source-sha256=af1158b7eb00b539488a53b9a60721166e42e6bd4e981242dd573019f0f3c5d5 -->
# Formations et attribution

## Formations

Une formation est ce qu'un certificat reconnaît. `Phil\CertificatesBundle\Application\Courses` les garde, chacune
connue par son *slug* :

```php
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;

$courses->define(
    'first-aid',                                        // the slug, in the register's URLs
    'First aid',                                        // the name, printed on the certificate
    'The gestures that save lives.',                    // the description
    'Score 70 % or more on the final assessment.',     // the narrative of the criteria, stated by the credential
    new PassCriteria(minimumScore: 70, minimumMinutes: 420, requiredModules: ['cpr', 'burns']),
    validityMonths: 24,                                 // forever when null
    image: 'https://example.org/badges/first-aid.png', // the badge's image, an absolute URL
    recertificationCredits: Credits::fromString('12'), // credits a recertification asks for; none when null
    endsAt: new DateTimeImmutable('2030-01-01 UTC'),    // when the course ends; never when null
);
```

| Champ | Règle |
|---|---|
| *Slug* | Jusqu'à 64 lettres minuscules et chiffres, avec des tirets simples entre eux; unique |
| Nom | De 1 à 255 caractères |
| Énoncé | Non vide : Open Badges exige les critères d'une réalisation |
| Score minimal | De 1 à 100, en pourcentage |
| Minimum de minutes | Au moins 1 |
| Modules exigés | Jusqu'à 100 codes de 1 à 64 caractères, chacun une seule fois |
| Validité | De 1 à 1200 mois |
| Image | Une URL HTTP absolue d'au plus 2048 caractères |
| Crédits de recertification | Plus de 0, avec jusqu'à deux décimales; null quand une recertification exige des acquis |
| Fin | N'importe quel moment; aucun certificat n'est décerné à partir de là, ni valide au-delà |

Une valeur hors de ces règles lève `InvalidValue`; un *slug* déjà pris, `CertificateRefused`. `revise()` change ce
qu'une formation dit et exige : les certificats déjà décernés gardent l'attestation qu'ils ont reçue. `retire()` empêche
une formation de décerner des certificats, `reopen()` le lui permet de nouveau; ceux qui ont été décernés restent
valides. Une formation arrivée à sa fin n'en décerne plus non plus; [Expiration et recertification](recertification.md)
traite de la fin des formations et des certificats.

`phil:certificates:course` définit ou révise une formation depuis la console
([Commandes de console](console.md#formations)).

## Attribution

`Phil\CertificatesBundle\Application\Awarding` décerne le certificat d'une formation à une personne apprenante dont les
acquis satisfont à ses critères :

```php
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;

$certificate = $awarding->award(
    'first-aid',
    'Ada Lovelace',                                     // the name printed on the certificate
    'ada@example.org',                                  // the identity the credential is issued to
    new Attainment(score: 84, minutes: 450, completedModules: ['cpr', 'burns']),
    listed: true,                                       // shown on the course's page in the register
    holder: 'member-42',                                // the member's stable identifier, for its credits
);
```

L'application mesure les acquis (le score d'une évaluation, les minutes suivies, les modules terminés) et décide quand
décerner : après une évaluation finale, sur confirmation d'une personne formatrice. Le bundle les compare aux critères
et refuse avec `CertificateRefused` une formation inconnue, retirée ou terminée, ou des critères non satisfaits; le message
énumère ce qui manque : `score`, `minutes`, `module:<code>`.

L'identité est un identifiant décentralisé (`did:…`), une adresse de courriel ou une URL que la personne apprenante
contrôle. Une adresse ou une URL est gardée dans l'attestation sous forme d'empreinte SHA-256 salée, le sel étant tiré
pour chaque certificat, et nulle part ailleurs : le certificat ne stocke aucune adresse de courriel. Quiconque détient
l'attestation et connaît l'adresse peut la comparer à l'empreinte, comme le fait la page de vérification de
`phil/open-badges-bundle`.

Le certificat contient alors :

| Champ | Valeur |
|---|---|
| `id` | Un UUID; l'identifiant de l'attestation est `urn:uuid:<id>` |
| `code` | Un `VerificationCode` de douze caractères, imprimé `7KQ2-M9XA-04TZ` |
| `awardedAt`, `validUntil` | Maintenant, selon l'horloge; maintenant plus les mois de la formation, sans dépasser la fin de la formation, ou null |
| `statusIndex` | Le prochain indice dans les listes d'état ([Révocation](revocation.md)) |
| `credential` | L'`OpenBadgeCredential`, signée comme l'indique `phil_open_badges.securing` : un jeton ou un document |
| `listed` | Indique si la page de la formation l'affiche |
| `holder` | L'identifiant stable du membre quand l'application en a donné un; une adresse de courriel est refusée |
| `renews` | Le certificat qu'il renouvelle, quand une recertification l'a décerné |
| `remindedAt` | Le moment où son titulaire a été rappelé de sa fin pour la dernière fois |

La réalisation de l'attestation est la formation : son identifiant est l'URL de la page de la formation dans le
registre, et son nom, sa description, ses critères et son image sont ceux de la formation. Les révisions ultérieures de
la formation ne la changent pas.

L'affichage relève du choix de la personne apprenante : un certificat n'est pas affiché, à moins que l'application en
décide autrement, et on le trouve seulement par son code.

## Deux attributions à la fois

Chaque certificat prend le prochain indice d'état, un de plus que le plus haut déjà donné. Avec une fabrique de
verrous (celle qu'enregistre FrameworkBundle quand `symfony/lock` est installé, ou celle que nomme `lock_factory`),
`Awarding` prend l'indice, signe l'attestation et stocke le certificat sous le verrou `phil_certificates.status_index`,
de sorte que deux attributions simultanées ne visent jamais le même indice. Le stockage du verrou est partagé par chaque
serveur qui décerne : le stockage `flock` par défaut de FrameworkBundle ne sert qu'un seul serveur, et un site sur
plusieurs serveurs configure un stockage Redis, PDO ou PostgreSQL. Le verrou est libéré une fois le certificat stocké;
une attribution faite dans une transaction que l'application a ouverte est validée plus tard, on la fait donc hors
d'une transaction.

Sans fabrique de verrous (`lock_factory: null`, ou aucun composant de verrou), ou quand un serveur décerne avec un
stockage que les autres ne partagent pas, l'indice d'état unique du stockage refuse la seconde de deux attributions qui
ont pris le même indice. Le dépôt lève alors `Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken`, et `Awarding`
reprend cette attribution : la formation relue, le prochain indice, un nouveau code et un nouvel identifiant,
l'attestation signée de nouveau. Il le fait `Awarding::ATTEMPTS` fois (trois) au plus; au-delà, `StatusIndexTaken`
atteint l'appelant et rien n'a été stocké ni envoyé.

Le dépôt Doctrine lève `StatusIndexTaken` après avoir réinitialisé le gestionnaire d'entités, que Doctrine ferme après
un échec d'écriture, de sorte que la tentative suivante s'exécute sur un gestionnaire ouvert; les entités que
l'application avait chargées de ce gestionnaire avant l'attribution sont détachées et relues. Un dépôt propre à
l'application lève `StatusIndexTaken` quand son stockage refuse un certificat pour un indice ou un code qu'un autre
détient, une fois prêt pour une autre tentative.

## Après une attribution ou une révocation

Avec un répartiteur d'événements, `Awarding` envoie `Phil\CertificatesBundle\Domain\Event\CertificateAwarded` une fois
le certificat stocké (suivi de `CertificateRenewed` pour une recertification), et `Revocation` envoie
`CertificateRevoked`. Chacun porte l'identifiant du certificat, son code de
vérification et le *slug* de sa formation, et rien sur la personne apprenante : un écouteur qui envoie le PDF, ou qui
met en file un message qui le fait, lit le certificat par `CertificateRepository::findByCode()`.

```php
#[AsEventListener]
final readonly class SendCertificate
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CertificateAwarded $awarded): void
    {
        $this->bus->dispatch(new SendCertificateMessage($awarded->certificateId));
    }
}
```

Les [commandes de console](console.md) décernent et révoquent par les mêmes services : leurs certificats sont donc
annoncés aussi.
