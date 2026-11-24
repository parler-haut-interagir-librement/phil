<!-- phil-dev source-sha256=b27ef15eb052b859deb98a34d3d44802fb0d6a4866dfe87b99227e20b77b729d -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

## Les ports que l'application implémente

| Port | Valeur par défaut | Ce qu'il dit |
|---|---|---|
| `Application\Contract\CurrentReviewer` | `SecurityCurrentReviewer`, l'identifiant d'utilisateur de Symfony Security | Qui est connecté : `member(): ?MemberId`. |
| `Application\Contract\Participations` | `NoParticipations` | La part d'un membre dans un sujet : `of(SubjectRef, MemberId): ?Participation`, à partir du commerce ou de la billetterie. |
| `Application\Contract\ResponderPolicy` | `RoleResponders`, l'attribut `responder_attribute` | Qui répond aux avis d'un sujet : `mayRespond(SubjectRef, MemberId): bool`. |
| `Application\Contract\SubjectDirectory` | `IdentifierDirectory` | Le nom d'un sujet et l'adresse de sa page. |
| `Application\Contract\ReviewerDirectory` | `IdentifierDirectory` | Le nom d'un auteur. |

Nommez le service dans la configuration (`current_reviewer`, `participations`, `responders`, `subjects`, `reviewers`),
ou faites-lui pointer l'interface par un alias dans `config/services.yaml`.

## Les dépôts

`Domain\Contract\ReviewRepository` et `Domain\Contract\RatingRepository` sont des alias des dépôts DBAL. Un dépôt de
votre cru garde leurs contrats : un second avis d'un sujet par son auteur lance `DuplicateReview` depuis une contrainte
d'unicité du dépôt, un second vote remplace le premier, les décomptes des votes s'écrivent à partir des votes seuls, et
un calcul des notes est idempotent et sérialisé sur son sujet.

## Les événements

| Événement | Quand |
|---|---|
| `Application\Event\ReviewWritten` | Un avis a été écrit ou modifié. |
| `Application\Event\ReviewWithdrawn` | Un avis a été retiré, ou retenu pendant que la modération l'examine. |
| `Application\Event\ReviewPublished` | Un avis retiré a été publié de nouveau. |
| `Application\Event\ContestFiled` | Un auteur a contesté un retrait. |

Chacun porte les identifiants de l'avis et de son sujet, jamais son texte : un écouteur qui a besoin de plus lit l'avis
par `Reviews::get()`. Un message de Messenger construit à partir d'un événement porte les mêmes identifiants.

## Les intégrations

| Pour | Faites |
|---|---|
| Signaler des avis | Enregistrez `phil/moderation-bundle` ; la cible `review` et son écouteur sont enregistrés avec lui. |
| Exporter et effacer | Installez `phil/privacy-bundle` ; `ReviewPrivacy` porte les étiquettes `phil_privacy.exporter` et `phil_privacy.eraser`. |
| Donner des données structurées | Installez `phil/structured-data` ; `AggregateRatings` est enregistré. |
| Protéger le formulaire | Enregistrez `phil/spam-protection-bundle` ; `spam_protection: false` laisse le formulaire sans lui. |
| Limiter les formulaires | Déclarez des limiteurs dans `phil/rate-limit-bundle` et nommez-les sous `rate_limit`. |
| Changer une page | Remplacez le gabarit sous `templates/bundles/PhilReviewsBundle/`. |
| Changer un libellé | Traduisez la clé dans le domaine `phil_reviews`. |
