<!-- phil-dev source-sha256=9070ea473b391f3023a5b80d35b7b0c42a592bea819ee49512b632efb6e51b8b -->
# Avis

`Phil\ReviewsBundle\Application\Reviews` est le point d'entrée. Chaque méthode prend des identifiants et des valeurs,
jamais une requête : les pages l'appellent, et les contrôleurs, commandes ou API de l'application le peuvent aussi.

## Un sujet

Un sujet est nommé, jamais détenu : un type que la configuration déclare et l'identifiant que l'application lui donne.

```php
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

$subject = SubjectRef::of('game', 'catan');
```

L'identifiant fait de 1 à 190 octets de caractères visibles. `SubjectDirectory` donne aux pages son nom et l'adresse de
sa propre page.

## Écrire un avis

```php
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;

$review = $reviews->write(
    $subject,
    MemberId::fromString('m-1842'),
    new ReviewContent(4, ['rules' => 5, 'replayability' => 3], 'Easy to teach', 'We played it four times in a row.', ['quick-to-teach']),
    Compensation::received('A copy from the publisher'),
);
```

| Partie | Règle |
|---|---|
| Note globale | Obligatoire, de 1 à l'échelle du type. |
| Critères | Les notes des critères du type que le membre a choisi de noter ; un critère laissé sans note est omis. |
| Titre | Facultatif, 150 caractères au plus. |
| Texte | Facultatif, au plus le `body_length` du type. |
| Superlatifs | Jusqu'à `max_superlatives` des étiquettes du type, jamais plus de trois. |
| Compensation | Si l'auteur a reçu quelque chose en échange, et quoi, 200 caractères au plus. |

Un texte est rogné, en UTF-8 valide, sans caractères de contrôle hormis les sauts de ligne et les tabulations. Un
identifiant de membre fait de 1 à 190 octets et n'est jamais une adresse courriel : un identifiant conservé à côté de
chaque avis ne doit pas être un moyen de joindre la personne.

`write()` refuse, avec une `ReviewRefused` dont le message peut être affiché :

- un second avis du même sujet par le même membre : il modifie plutôt le sien. La contrainte d'unicité de
  `phil_review` décide, de sorte que deux requêtes au même moment ne peuvent pas en écrire chacune un ;
- un avis pendant que les avis du sujet sont suspendus ;
- là où seuls les participants donnent un avis, un membre qui n'a pas participé, une participation à venir, ou une
  participation plus ancienne que la fenêtre.

Un contenu qui ne convient pas au type lance `InvalidReview`.

L'avis est publié dès qu'il est écrit, et les notes du sujet sont recalculées ([Notes](ratings.md)). `ReviewWritten`
est émis avec les identifiants de l'avis et du sujet.

## Modifier un avis

```php
$reviews->edit($review->id, $author, new ReviewContent(5), Compensation::none());
```

Seul l'auteur modifie un avis publié ; sa modification est datée et la page dit « edited ».

## Les participants et la mention vérifiée

`Participations` est le port que le commerce ou la billetterie de l'application implémente :

```php
final class OrderParticipations implements Participations
{
    public function of(SubjectRef $subject, MemberId $member): ?Participation
    {
        $delivered = $this->orders->lastDeliveryOf($member->value, $subject->id);

        return null === $delivered ? null : new Participation(Verification::Purchase, $delivered);
    }
}
```

Le moment d'une participation est celui où la fenêtre s'ouvre : la livraison, la fin de la partie ou de
l'événement. Un avis est marqué « verified purchase » ou « took part » dès que le port connaît une participation, quel
que soit le type. Pour un type avec `participants_only`, un membre donne son avis à partir de ce moment et jusqu'à
`window` jours après, dernière seconde comprise ; `window: null` lève la limite.

## Les compensations

Un avis donné contre une compensation — un produit gratuit, un rabais, un paiement, une place gratuite — le dit partout
où il est affiché, avec ce qui a été reçu dans les mots de l'auteur. Le formulaire le demande à chaque auteur, et la
mention ne peut pas être cachée par une option de gabarit.

## Les votes d'utilité

```php
$reviews->vote($review->id, $voter, true);   // helpful
$reviews->vote($review->id, $voter, false);  // not helpful: replaces the first vote
$reviews->retractVote($review->id, $voter);
```

Un membre vote une fois sur un avis, jamais sur le sien, et seulement sur un avis publié. La clé primaire de
`phil_review_vote` départage deux votes au même moment ; les décomptes de l'avis sont écrits de nouveau à partir des
votes par une seule instruction, de sorte qu'ils ne dérivent jamais. Un avis enregistré après un vote garde les
décomptes des votes.

## Les réponses

```php
$reviews->respond($review->id, $merchant, 'Thank you — a new box is on its way.');
$reviews->removeResponse($review->id, $merchant);
```

Une réponse publique par avis, affichée sous lui ; une seconde remplace la première. `ResponderPolicy` dit qui répond :
par défaut les membres à qui Symfony Security accorde `responder_attribute`, le sujet donné aux voters, de sorte qu'un
voter de l'application peut admettre le marchand de chaque produit ou l'organisateur de chaque événement.

## Suspendre un sujet

```php
$ratings->suspend($subject, true);   // no new review; those given stay and still count
$ratings->suspend($subject, false);
```

## La lecture

| Méthode | Ce qu'elle donne |
|---|---|
| `of($subject, $order, $limit, $offset)` | Les avis publiés, par `Helpful`, `Newest`, `Highest` ou `Lowest`. Chaque ordre liste chaque avis publié. |
| `count($subject)` | Le nombre d'avis publiés. |
| `get($id)` | Un avis, quel que soit son état. |
| `mineOf($subject, $member)` | L'avis du membre sur le sujet, quel que soit son état. |
| `voteOf($id, $member)` | Le vote du membre : `true`, `false` ou `null`. |
| `mayRespond($subject, $member)` | Si le membre répond aux avis du sujet. |
| `refusal($subject, $member)` | Pourquoi le membre ne peut pas donner d'avis maintenant — `suspended`, `window` ou `unknown` —, ou `null`. |
