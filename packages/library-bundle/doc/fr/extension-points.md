<!-- phil-dev source-sha256=975e5ee13fba539346a1d8e86e6a4199d05a3f959fdf93bf62b1e92002baf00d -->
# Points d'extension

Le bundle est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Nommer le membre d'une requête | Implémentez `Application\Contract\MemberResolver` et nommez son service dans `member_resolver` ; il n'y a pas de valeur par défaut, et aucune ne lit une adresse courriel |
| Envoyer les rappels autrement | Implémentez `Application\Contract\ReminderSender` et créez-en un alias ; avec Messenger, gérez plutôt `SendLoanReminder` |
| Déplacer le stock des exemplaires liés | Installez `phil/stockroom-bundle`, réglez `stockroom` sur le `Phil\Stockroom\Stockroom` de l'application, ou implémentez `Application\Contract\StockLink` et créez-en un alias |
| Garder un prêt et son mouvement de stock ensemble | Installez `phil/stockroom-bundle` sur la connexion de la bibliothèque, ou implémentez `Application\Contract\LoanTransaction` et créez-en un alias |
| Chercher dans le catalogue autrement | Implémentez `Application\Contract\TitleSearch` et créez-en un alias |
| Conserver les titres, les exemplaires ou les prêts ailleurs | Implémentez les ports de `Domain\Contract` et créez-en des alias |
| Réagir à un prêt | Écoutez `Domain\Event\CopyLent` et `Domain\Event\CopyReturned`, qui ne portent que des identifiants |
| Changer une page | Remplacez son gabarit sous `templates/bundles/PhilLibraryBundle/`, ou faites pointer `pages.layout` vers la mise en page de l'application |
| Joindre les pages depuis une interface d'administration | `Infrastructure\EasyAdmin\LibraryAdmin::menuItems()` |
| Ajouter l'historique aux demandes de vie privée | Installez `phil/privacy-bundle` : `LoanHistoryPrivacy` est étiqueté comme son exportateur et son effaceur |

## Les ports du domaine

| Port | Adaptateur |
|---|---|
| `TitleRepository` | `DoctrineTitleRepository`, l'ORM |
| `CopyRepository` | `DoctrineCopyRepository`, l'ORM |
| `LoanRepository` | `DoctrineLoanRepository`, l'ORM pour un prêt et les listes courtes, DBAL pour les décomptes et les changements en bloc |

`LoanRepository::save()` conserve un prêt et l'exemplaire qu'il réclame en une seule écriture ; un adaptateur de
l'application fait de même, de sorte que deux prêts d'un même exemplaire faits en même temps ne soient pas tous deux
conservés.
