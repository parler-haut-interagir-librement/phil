<!-- phil-dev source-sha256=9c6b8ff9a8211e3e9d20bc2cb860516a3c01db6a701540710a16b78598aaf271 -->
# Enregistrement

`Mentions` est ce que l'application appelle quand elle enregistre un texte, et quand elle le supprime :

```php
use Phil\MentionsBundle\Application\Mentions;
use Phil\Resource\ResourceReference;

$changes = $mentions->inText(
    ResourceReference::of('comment', $comment->id),  // the subject
    ResourceReference::of('user', $author->id),      // the author
    $comment->body,
    markdown: true,
    context: ResourceReference::of('page', $page->id),
);

$mentions->inDocument(ResourceReference::of('page', $page->id), $author, $document);
$mentions->forget(ResourceReference::of('comment', $comment->id));
```

## Sujets et contextes

Le **sujet** est ce qui mentionne : un commentaire, une proposition, une page, un message — ce dans quoi l'application
conserve le texte. Le **contexte** est ce qui décide qui peut le voir. C'est le sujet lui-même, sauf si l'application
en nomme un autre : un commentaire est vu par quiconque voit sa page, une proposition par les membres de son projet.

## Ce qui est enregistré

Chaque fois qu'un texte est enregistré, `MentionRecorder` :

1. compte ses mentions, chaque membre une fois, et refuse le texte au-delà de `limits.per_text` ;
2. demande à l'annuaire les membres de ses identifiants en un appel, et ceux de ses marques en un autre ;
3. écarte l'auteur, et chaque membre pour qui `maySee($member, $context)` est faux ;
4. compare les membres restants à ceux conservés pour le sujet : les nouveaux sont ajoutés et ceux qui ne sont plus
   mentionnés sont retirés, en un seul changement du stockage ;
5. diffuse `MemberMentioned` pour chaque nouveau membre, une fois le changement conservé ;
6. renvoie `MentionChanges` : les membres ajoutés et les références retirées.

L'enregistrement est donc idempotent : enregistrer de nouveau le même texte ne change rien et n'annonce rien, et une
modification n'annonce que les membres qu'elle ajoute. Un membre retiré puis mentionné de nouveau est annoncé de
nouveau. Un identifiant que personne ne porte, un membre supprimé et un membre qui ne peut pas voir le contenu ne sont
pas conservés. Ils sont donc annoncés quand une modification ultérieure les mentionne, s'ils existent alors et peuvent
voir le contenu.

| Méthode | Renvoie |
|---|---|
| `inText($subject, $author, $text, $markdown = false, $context = null)` | `MentionChanges` |
| `inDocument($subject, $author, $document, $context = null)` | `MentionChanges` |
| `mentionedIn($subject)` | Les membres qu'il mentionne, `list<ResourceReference>`, du premier mentionné au dernier |
| `mentioning($member, $limit = 50)` | Ses mentions, `list<RecordedMention>`, de la plus récente à la plus ancienne, de 1 à 500 |
| `forget($subject)` | Rien ; personne n'est notifié |

`MentionRecorder::record($subject, $author, $mentions, $context)` accepte des mentions trouvées ailleurs, sous forme de
liste de `Mention`.

## L'événement

`Phil\MentionsBundle\Application\Event\MemberMentioned` ne transporte que des identifiants :

| Propriété | Contient |
|---|---|
| `subject` | Ce qui mentionne, `ResourceReference` |
| `member` | Le membre mentionné |
| `author` | La personne qui a écrit |
| `context` | Ce qui a décidé de la visibilité |
| `recipient` | L'identifiant auquel vont les notifications du membre, `Member::$recipient` |

Il est diffusé sur le répartiteur PSR-14, l'`event_dispatcher` de Symfony dans une application. Le bundle l'écoute pour
notifier : voir [Notifications](notifications.md). Une application l'écoute à ses propres fins — un fil d'activité, un
compte des mentions non lues, un *webhook*.

## Les stockages

`MentionRepository` conserve des lignes `RecordedMention`, chacune avec un sujet, un membre, un auteur et une seconde :

| Stockage | Pour |
|---|---|
| `DoctrineMentionRepository` | La table `phil_mention` : lue par le gestionnaire d'entités, chaque changement dans une transaction |
| `InMemoryMentionRepository` | Les tests et les exemples, vidé entre deux requêtes |

Deux requêtes qui enregistrent le même sujet en même temps peuvent toutes deux tenter d'ajouter un membre. Le stockage
Doctrine ajoute ses lignes par un upsert qui garde la ligne déjà présente — `INSERT … ON CONFLICT (subject, member) DO
NOTHING` sur PostgreSQL et SQLite, `INSERT … ON DUPLICATE KEY UPDATE id = id` sur MySQL et MariaDB — et ne retourne que
les lignes qu'il a insérées : le membre est enregistré une fois, et seule la requête qui a inséré la ligne annonce
`MemberMentioned`, de sorte que le membre est notifié une fois. Rien n'est retenté et aucune erreur n'atteint
l'application. Sur une autre plateforme, l'instruction est une insertion simple, que la contrainte unique
`(subject, member)` refuse ; sur MySQL, la connexion doit compter les lignes modifiées, non les lignes trouvées, ce qui
est la valeur par défaut de `pdo_mysql`.

Le stockage en mémoire porte l'étiquette `kernel.reset` : il est vidé entre deux requêtes, sous PHP-FPM comme dans un
*worker* tel que celui de FrankenPHP, de sorte que rien de ce qu'écrit une requête n'atteint la suivante. Un test qui
lit ce qu'une requête antérieure a écrit utilise le stockage Doctrine sur SQLite.
