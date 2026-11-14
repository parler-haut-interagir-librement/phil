<!-- phil-dev source-sha256=a7ab121c7a25d7a4f829e1984bdc152835618fac6f4abb5fecb756891603d60e -->
# Référence

## Domain

| Classe | Rôle |
|---|---|
| `Domain\Model\Course` | Une formation : *slug*, nom, description, énoncé, critères, validité, image, retrait, crédits de recertification, fin. |
| `Domain\Model\Certificate` | Un certificat : code, formation, nom, dates, indice d'état, attestation, affichage, révocation, titulaire, certificat qu'il renouvelle, dernier rappel. |
| `Domain\Model\LearningActivity` | Une activité de formation continue : membre, titre, crédits, achèvement, formation, source. |
| `Domain\ValueObject\PassCriteria` | Le score, les minutes et les modules minimaux d'une formation; ce qui manque à des acquis. |
| `Domain\ValueObject\Attainment` | Ce qu'une personne apprenante a acquis : score, minutes, modules terminés. |
| `Domain\ValueObject\VerificationCode` | Le code de douze caractères d'un certificat, généré et relu. |
| `Domain\ValueObject\Credits` | Des crédits de formation continue, au centième. |
| `Domain\ValueObject\MemberId` | L'identifiant stable d'un membre; jamais une adresse de courriel. |
| `Domain\ValueObject\Period` | Une période, son début compris et sa fin exclue. |
| `Domain\Status\CertificateStatus` | Valide, expiré ou révoqué. |
| `Domain\Contract\CourseRepository`, `CertificateRepository`, `LearningActivityRepository` | L'endroit où les formations, les certificats et les activités sont gardés. |
| `Domain\Exception\CertificatesException` | Le marqueur qu'implémente chaque exception du bundle. |
| `Domain\Exception\InvalidValue` | Une valeur incohérente. |
| `Domain\Exception\CertificateRefused` | Une attribution ou une révocation refusée. |
| `Domain\Exception\StatusIndexTaken` | Un certificat refusé parce qu'un autre a pris son indice ou son code; l'attribution reprend. |
| `Domain\Event\CertificateAwarded` | Un certificat décerné et stocké, par identifiants. |
| `Domain\Event\CertificateRevoked` | Un certificat révoqué et stocké ainsi, par identifiants. |
| `Domain\Event\CertificateRenewed` | Le certificat d'une recertification stocké, par identifiants. |
| `Domain\Event\CertificateExpiring` | Un certificat à moins d'un seuil de rappel de sa fin, par identifiants et jours restants. |
| `Domain\Event\LearningActivityRecorded` | Une activité de formation continue stockée, par identifiants. |

## Application

| Classe | Rôle |
|---|---|
| `Application\Courses` | Définit, révise, retire et rouvre des formations. |
| `Application\Awarding` | Décerne un certificat avec son attestation signée, de nouveau quand son indice était pris; recertifie. |
| `Application\ExpiryReminders` | Envoie les rappels des certificats sur le point de prendre fin. |
| `Application\ContinuingEducation` | Consigne, liste et additionne les crédits de formation continue; l'avancement d'un titulaire. |
| `Application\Revocation` | Révoque un certificat. |
| `Application\CertificatePdf` | Produit le rendu du PDF d'un certificat, l'attestation jointe, PDF/A-3b sur demande. |
| `Application\StatusListLayout` | La façon dont les indices d'état remplissent les listes. |
| `Application\CertificateStatusLists` | Les listes d'état de la révocation, pour `phil/open-badges-bundle`. |
| `Application\Contract\CertificateUrls` | Les URL vers lesquelles pointe un certificat. |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Doctrine\DoctrineCourseRepository`, `DoctrineCertificateRepository`, `DoctrineLearningActivityRepository` | Les dépôts par Doctrine. |
| `Infrastructure\Routing\RoutedCertificateUrls` | Les URL tirées des routes du registre. |
| `Infrastructure\Http\RegisterController` | Les pages et les téléchargements du registre. |
| `Infrastructure\Console\CourseCommand` | `phil:certificates:course`. |
| `Infrastructure\Console\AwardCommand` | `phil:certificates:award`. |
| `Infrastructure\Console\RevokeCommand` | `phil:certificates:revoke`. |
| `Infrastructure\Console\StatusCommand` | `phil:certificates:status`. |
| `Infrastructure\Console\ExportCommand` | `phil:certificates:export`. |
| `Infrastructure\Console\RecertifyCommand` | `phil:certificates:recertify`. |
| `Infrastructure\Console\RemindCommand` | `phil:certificates:remind`. |
| `Infrastructure\Console\CreditCommand` | `phil:certificates:credit`. |
| `Infrastructure\Console\CreditsCommand` | `phil:certificates:credits`. |
| `Infrastructure\Console\Numbers` | Interne : les nombres entiers et les jours saisis sur la ligne de commande. |
| `Infrastructure\DependencyInjection\CertificatesServices` | Les services du bundle. |
| `PhilCertificatesBundle` | La configuration, les vérifications et l'association Doctrine. |
