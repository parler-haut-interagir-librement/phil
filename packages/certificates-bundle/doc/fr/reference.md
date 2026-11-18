<!-- phil-dev source-sha256=3a005f3f068d089464aad0880c29abc91407952986d07c2ff598913479d49049 -->
# Référence

## Domain

| Classe | Rôle |
|---|---|
| `Domain\Model\Course` | Une formation : *slug*, nom, description, énoncé, critères, validité, image, retrait, crédits de recertification, fin, source d'achèvement. |
| `Domain\Model\Certificate` | Un certificat : code, formation, nom, dates, indice d'état, attestation, affichage, révocation, titulaire, certificat qu'il renouvelle, dernier rappel, achèvement sur lequel il a été décerné. |
| `Domain\Model\LearningActivity` | Une activité de formation continue : membre, titre, crédits, achèvement, formation, source. |
| `Domain\ValueObject\PassCriteria` | Le score, les minutes et les modules minimaux d'une formation; ce qui manque à des acquis. |
| `Domain\ValueObject\Attainment` | Ce qu'une personne apprenante a acquis : score, minutes, modules terminés. |
| `Domain\ValueObject\VerificationCode` | Le code de douze caractères d'un certificat, généré et relu. |
| `Domain\ValueObject\Credits` | Des crédits de formation continue, au centième. |
| `Domain\ValueObject\MemberId` | L'identifiant stable d'un membre; jamais une adresse de courriel. |
| `Domain\ValueObject\Period` | Une période, son début compris et sa fin exclue. |
| `Domain\ValueObject\OpaqueId` | Un identifiant que donne un autre système : la source d'achèvement d'une formation, un achèvement. |
| `Domain\Status\CertificateStatus` | Valide, expiré ou révoqué. |
| `Domain\Contract\CourseRepository`, `CertificateRepository`, `LearningActivityRepository` | L'endroit où les formations, les certificats et les activités sont gardés. |
| `Domain\Contract\CourseCompletions` | Si un membre a achevé la formation d'un autre système, et l'identifiant de cet achèvement. |
| `Domain\Exception\CertificatesException` | Le marqueur qu'implémente chaque exception du bundle. |
| `Domain\Exception\InvalidValue` | Une valeur incohérente. |
| `Domain\Exception\CertificateRefused` | Une attribution ou une révocation refusée. |
| `Domain\Exception\StatusIndexTaken` | Un certificat refusé parce qu'un autre a pris son indice, son code ou son achèvement; l'attribution reprend. |
| `Domain\Event\CertificateAwarded` | Un certificat décerné et stocké, par identifiants. |
| `Domain\Event\CertificateRevoked` | Un certificat révoqué et stocké ainsi, par identifiants. |
| `Domain\Event\CertificateRenewed` | Le certificat d'une recertification stocké, par identifiants. |
| `Domain\Event\CertificateExpiring` | Un certificat à moins d'un seuil de rappel de sa fin, par identifiants et jours restants. |
| `Domain\Event\LearningActivityRecorded` | Une activité de formation continue stockée, par identifiants. |

## Application

| Classe | Rôle |
|---|---|
| `Application\Courses` | Définit, révise, retire et rouvre des formations; nomme leur source d'achèvement. |
| `Application\Awarding` | Décerne un certificat avec son attestation signée, de nouveau quand son indice était pris, sur un achèvement quand sa formation nomme une source; recertifie. |
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
| `Infrastructure\Doctrine\BinaryKeysListener` | Les clés du registre en `utf8mb4_bin` sur MySQL et MariaDB. |
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
