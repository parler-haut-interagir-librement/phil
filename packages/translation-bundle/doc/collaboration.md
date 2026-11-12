# Comments and credits

## One target per segment

A segment holds one target. A proposal is that target in the state `suggested`; a better proposal replaces it, and a
translator who disagrees writes another or says why in a comment. There are no competing suggestions to vote on: the
conversation about a segment — a question to the author, a choice explained, another wording — goes into its
comments.

## Comments

`Phil\TranslationBundle\Application\Comments` keeps the comments of the segments of a document in a target language,
in the table `phil_translation_comment`.

```php
$comments->add('article:42', LanguageTag::of('fr'), 'b1/text', 'Formal or informal “you” here?');
$bySegment = $comments->forDocument('article:42', LanguageTag::of('fr'));   // array<key, list<CommentRecord>>
$comments->remove('article:42', LanguageTag::of('fr'), $id);
```

A comment is 1 to 4000 characters of UTF-8, its white space at both ends left out, and is signed by the current user
— the identifier the application's `Translators` gives, or nobody when there is none. `forDocument()` gives them by
segment key, oldest first. `remove()` throws `NotFound` for a comment that is not of that document and language. On
the screen, every translator comments and only reviewers remove comments.

## Who did what

Each segment row keeps who wrote its target (`translated_by`) and who reviewed it (`reviewed_by`), as the identifiers
of the application's `Translators`:

| Action | Written by | Reviewed by |
|---|---|---|
| Translate, propose | the current user | nobody |
| Review | kept | the current user |
| Confirm a stale target | kept | nobody |
| Clear | nobody | nobody |
| Import from XLIFF | the current user | the current user, for a reviewed unit, when they may review |
| Machine translation | nobody, and marked as machine output | nobody |

A unit an XLIFF file marks `final` or `signed-off` is merged as reviewed only when the user may review: for a user
who may only translate, it is merged as translated, as if they had written it on the screen, so that a file never
grants a right the screen refuses.

The bundle keeps identifiers only: names, emails and profiles stay with the application.

## Public credit

Translators are thanked with a public credit, and never paid through the bundle. `credits($document, $language)`
gives the people who wrote or reviewed the document's usable targets and accept a credit: the name `Translators`
gives for each, with how many segments they translated and reviewed, most segments first, then by name. A person whose
`credit()` is `null` — who asked not to be credited — is left out, and machine output credits nobody.

```twig
{{ include('@PhilTranslation/credits.html.twig', {document: 'article:42', language: 'fr-CA'}) }}
{# Translated by Ana, Ben and Cléo. Thank you! #}

{% for credit in phil_translation_credits('article:42', app.request.locale) %}
    {{ credit.name }} ({{ credit.translated }} translated, {{ credit.reviewed }} reviewed)
{% endfor %}
```

The Twig function `phil_translation_credits()` takes a BCP 47 tag or a locale such as `fr_CA`, and gives no credit for
a language that is not one.

## The `Translators` service

```php
use Phil\TranslationBundle\Application\Contract\Translators;

final readonly class UserTranslators implements Translators
{
    public function __construct(private Security $security, private UserRepository $users) {}

    public function current(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    public function credit(string $translator): ?string
    {
        $user = $this->users->findOneByIdentifier($translator);

        return null !== $user && $user->acceptsCredit() ? $user->publicName() : null;
    }
}
```

```yaml
phil_translation:
    translators: App\Translation\UserTranslators
```

Without it, targets and comments are signed by nobody and no one is credited.
