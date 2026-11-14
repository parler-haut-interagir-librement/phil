<!-- phil-dev source-sha256=4dbdbc750e98fd9b6168dec97b2d26498a1444f89a5634103bb6fb4bbb8e5425 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\ValueObject\Mention` | Un identifiant à une position, ou un membre nommé par une marque |
| `Domain\ValueObject\MentionPattern` | La forme d'un identifiant, et l'expression qui le trouve après `@` |
| `Domain\Model\RecordedMention` | Un membre mentionné dans un sujet, mappé sur `phil_mention` |
| `Domain\Contract\MentionRepository` | Le stockage des mentions |
| `Domain\Exception\MentionsException` | Toutes les exceptions du bundle |
| `Domain\Exception\InvalidMention` | Ce dont on ne peut pas faire une mention, un motif, un membre ou une limite |
| `Domain\Exception\MentionsRefused` | Un texte trop long, ou qui mentionne trop de membres |

## Application

| Classe | Rôle |
|---|---|
| `Application\Contract\MemberDirectory` | Les membres de l'application |
| `Application\Contract\MentionPreferences` | Indique si un membre veut être notifié |
| `Application\Member` | Un membre tel que le donne l'annuaire |
| `Domain\ValueObject\MemberIdentifier` | L'identifiant d'un membre ou d'un auteur, refusé quand il contient `@` |
| `Application\Extraction\TextMentions` | Les identifiants d'un texte brut ou de Markdown |
| `Application\Extraction\UjoMentions` | Les marques de mention d'un document UJO |
| `Application\MentionLimits` | Les membres par texte et la longueur d'un texte |
| `Application\MentionRecorder` | Les mentions d'un sujet tenues à jour avec son texte |
| `Application\MentionChanges` | Les membres qu'un enregistrement a ajoutés et retirés |
| `Application\Mentions` | Le point d'entrée de l'application |
| `Application\Event\MemberMentioned` | Un membre nouvellement mentionné |
| `Application\AlwaysNotify` | Chaque membre notifié |
| `Application\Rendering\MentionLinker` | Les identifiants du HTML changés en liens vers les profils |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Doctrine\DoctrineMentionRepository` | La table, par le gestionnaire d'entités, chaque changement dans une transaction |
| `Infrastructure\Doctrine\MentionInsert` | L'upsert d'une ligne pour chaque plateforme |
| `Infrastructure\Memory\InMemoryMentionRepository` | Les mentions en mémoire, vidées entre deux requêtes (`kernel.reset`) |
| `Infrastructure\Notifications\NotifyMentionedMember` | La notification d'une nouvelle mention |
| `Infrastructure\Settings\SettingsMentionPreferences` | Le réglage `notifications.mentions` |
| `Infrastructure\Twig\MentionsExtension` | Le filtre `mentions`, déclaré par `#[AsTwigFilter]` et chargé quand il est appelé |
| `Infrastructure\BlockEditor\MemberReferenceSearch` | Le menu `@` de l'éditeur sur l'annuaire |
| `Infrastructure\Comments\CommentMentions` | Les mentions des commentaires |
| `Infrastructure\Comments\MemberAuthorDirectory` | Les auteurs des commentaires nommés à partir de l'annuaire |
| `Infrastructure\Comments\RecordCommentMentions` | Le pont : les mentions de chaque commentaire écrit, modifié ou supprimé |
| `Infrastructure\DependencyInjection\MentionsServices` | Les services |
| `Infrastructure\DependencyInjection\MentionsPass` | La notification et les préférences, selon les bundles activés |
| `PhilMentionsBundle` | La configuration et ce qui est ajouté aux autres bundles |

## JavaScript

| Module | Rôle |
|---|---|
| `assets/src/mention_controller.js` | L'autocomplétion `@` |
| `assets/src/lib/query.js` | La requête avant le curseur, l'identifiant inséré, l'identifiant d'une suggestion |
| `assets/src/lib/api.js` | La recherche des membres |
| `assets/styles/mentions.css` | La liste et `.mention` |

## La recette

| Fichier | Rôle |
|---|---|
| `recipe/0.1/manifest.json` | Le manifeste Flex : le bundle enregistré, la configuration et l'annuaire copié |
| `recipe/0.1/config/packages/phil_mentions.yaml` | `member_directory: App\Mentions\Members` |
| `recipe/0.1/src/Mentions/Members.php` | Un annuaire qui ne trouve personne, que l'application doit remplir |
