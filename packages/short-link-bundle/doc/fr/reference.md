<!-- phil-dev source-sha256=8d887a29cc4d5779def63220d2fce586704f861ad6bdd1ca44c5b5bf762a524e -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\ShortLinkBundle\PhilShortLinkBundle` | Le bundle : sa configuration `phil_short_link`, ses services et le mappage Doctrine. |
| `Application\Code\SqidCodec` | Les identifiants au format Sqids : encodage, décodage, vérification de la forme canonique, liste de blocage. |
| `Application\Code\CodeGenerator` | Un nombre aléatoire jusqu'à un maximum, écrit par le codec. |
| `Application\Code\DefaultBlocklist` | La liste par défaut de mots grossiers en anglais et en français. |
| `Application\ShortLinks` | Crée, modifie, active, désactive et supprime les liens, chaque enregistrement passant par les mêmes vérifications. |
| `Application\ShortLinkDraft` | Un lien tel qu'un formulaire le remplit. |
| `Application\ShortLinkResolver` | La réponse à une requête sur un domaine de lien court, sur le résolveur de phil/redirects-bundle. |
| `Application\Resolution` | Une redirection, un `410 Gone` ou un `404`, avec la visite qu'une redirection a comptée. |
| `Application\ShortLinkVisit` | Une visite comptée : l'identifiant du lien, son code, l'hôte, sa locale, la classe de son visiteur. |
| `Application\Contract\VisitorClassifier` | Dit qui est à l'origine d'une visite, d'après son `User-Agent` et ses en-têtes d'intention. |
| `Application\Visitor\KnownVisitorClassifier` | Le classeur du bundle : intentions, aperçus de liens et robots connus par leurs motifs. |
| `Application\ShortLinkDomain`, `ShortLinkDomains` | Les domaines de liens courts, par hôte et par locale. |
| `Application\ShortLinkQrCodes` | Le code QR de l'adresse d'un lien, en SVG ou en PNG. |
| `Application\Contract\VisitRecorder` | Ce qui est informé de chaque visite, après la réponse. |
| `Application\Contract\TargetCheck` | Une vérification d'une cible avant l'enregistrement. |
| `Application\Contract\CreationThrottle` | L'allocation de créations d'un auteur. |
| `Application\Exception\ShortLinkRefused` | Un lien refusé, avec sa raison et son message. |
| `Application\Exception\ShortLinkNotFound` | Aucun lien de cet identifiant. |
| `Application\Exception\InvalidCodec` | Un alphabet, une longueur minimale, un domaine ou des nombres avec lesquels le codec ne peut pas travailler. |
| `Domain\Model\ShortLink` | Le lien : son code, sa cible, ses conditions, le compte de ses visites de personnes et le compte de ses visites de machines. |
| `Domain\ValueObject\LinkTerms` | Le statut, la politique de chaîne de requête, le libellé, la locale et la validité d'un lien. |
| `Domain\Enum\LinkState` | Actif, planifié, désactivé, expiré ou sans visites restantes. |
| `Domain\Enum\VisitorClass` | Une personne, un robot, une prélecture ou un aperçu de lien. |
| `Domain\Contract\ShortLinkRepository` | Où les liens sont conservés, et le compte atomique d'une visite. |
| `Domain\Exception\InvalidShortLink` | Un lien qui ne peut pas exister tel qu'il est donné. |
| `Infrastructure\Doctrine\DoctrineShortLinkRepository` | Les liens avec Doctrine ORM. |
| `Infrastructure\Http\ShortLinkListener` | Répond aux domaines de liens courts avant le routage et informe les enregistreurs. |
| `Infrastructure\Http\ShortLinkAdminController` | L'administration. |
| `Infrastructure\Form\ShortLinkType` | Le formulaire d'un lien. |
| `Infrastructure\RateLimiter\RateLimiterCreationThrottle` | L'allocation sur un limiteur de débit de Symfony. |
| `Infrastructure\SafeHttp\SafeHttpTargetCheck` | La vérification d'une cible par phil/safe-http. |
| `Infrastructure\Analytics\AnalyticsVisitRecorder` | Une visite de personne comptée comme événement serveur de phil/analytics-bundle. |
