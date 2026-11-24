# Configuration

```yaml
# config/packages/phil_faq.yaml
phil_faq:
    entity_manager: default        # the Doctrine entity manager of the tables
    contact_url: /contact          # the link a search without answer shows; none when null
    layout: '@PhilFaq/layout.html.twig'
    max_questions: 500             # the most questions a page shows, 1 to 5000
    vote_lifetime: 86400           # how long the vote token of a page votes, in seconds, 60 to 604800
    markup: null                   # a Phil\Markup\Markup service that reads the answers
    kit_subdirectory: null         # the subdirectory of the recipes; the Toolkit's when null
    rate_limiters:
        votes: limiter.phil_faq_votes
        searches: limiter.phil_faq_searches
    admin:
        enabled: false
        role: ROLE_ADMIN
        layout: '@PhilFaq/admin/layout.html.twig'
        locales: [en]
```

## The options

| Option | Default | What it sets |
|---|---|---|
| `entity_manager` | `default` | The entity manager whose connection holds the tables. |
| `contact_url` | `null` | The address of the contact form, shown after a search that found nothing. |
| `layout` | `@PhilFaq/layout.html.twig` | The template the bundle's page extends, with the blocks `title` and `content`. |
| `max_questions` | `500` | The most questions a page shows, the first ones in order. |
| `vote_lifetime` | `86400` | How long the vote token of a page votes, in seconds. |
| `markup` | `null` | The `Phil\Markup\Markup` service that compiles and purifies the answers; when null, the bundle builds one with the compiler of `phil/markup` and its default purification policies. |
| `kit_subdirectory` | `null` | Where the recipes are, under the components directory; the subdirectory of `ux_toolkit` when null. |
| `rate_limiters.votes` | `limiter.phil_faq_votes` | The rate limiter factory of the votes, keyed by question. |
| `rate_limiters.searches` | `limiter.phil_faq_searches` | The rate limiter factory of the counted searches, keyed by language. |
| `admin.enabled` | `false` | Registers the administration; it needs Symfony Security. |
| `admin.role` | `ROLE_ADMIN` | The role that opens the administration. |
| `admin.layout` | `@PhilFaq/admin/layout.html.twig` | The template the pages of the administration extend. |
| `admin.locales` | `[en]` | The languages the administration edits, the first one by default. |

## What the bundle prepends

The bundle adds the token `phil_faq` to `framework.csrf_protection.stateless_token_ids`, maps `Domain\Model` for
Doctrine, and gives AssetMapper the path `@phil/faq-bundle` of the controller. It also declares two limiters, which an
application changes by declaring the same names in `framework.rate_limiter`:

```yaml
framework:
    rate_limiter:
        phil_faq_votes: { policy: sliding_window, limit: 60, interval: '1 minute' }      # per question
        phil_faq_searches: { policy: sliding_window, limit: 120, interval: '1 minute' }  # per language
```

A limiter is keyed by a question or a language, never by a reader: no address is kept, even for a minute. A search
past the limit still shows its page; only its count waits.

The container stops with a message that says what to change when `framework.csrf_protection` is off.

## The answers

An answer is written in Markdown, HTML or plain text — the formats of the administration —, or in any format of
`phil/markup` through `FaqEditor` and the import. It is compiled and purified once, when it is saved, at the `forum`
level of `phil/markup`: paragraphs, emphasis, links, lists, quotes and images, nothing that runs. Its source and its
format are kept beside the HTML, so it is edited as it was written.

## Personal data

The bundle keeps nothing about a reader. A vote keeps a total per question and the hash of a random token; a search
without answer keeps its normalized words, its language, a total and a day. No address, no identifier, no user agent
and no cookie are read or written, which is why the bundle declares no module to a privacy bundle. The administration
names no one either: what an editor changes is the application's to audit.

## The services

| Service | Class | Public |
|---|---|---|
| `Phil\FaqBundle\Application\FaqEditor` | `FaqEditor` | yes |
| `Phil\FaqBundle\Application\FaqPages` | `FaqPages` | yes |
| `Phil\FaqBundle\Application\FaqStructuredData` | `FaqStructuredData` | yes |
| `Phil\FaqBundle\Application\FaqMarkdown` | `FaqMarkdown` | yes |
| `Phil\FaqBundle\Application\FaqTransfer` | `FaqTransfer` | yes |
| `Phil\FaqBundle\Application\HelpfulVotes` | `HelpfulVotes` | yes |
| `Phil\FaqBundle\Application\UnansweredSearches` | `UnansweredSearches` | yes |
| `Phil\FaqBundle\Domain\Contract\FaqRepository` | alias of `DoctrineFaqRepository` | yes |
| `Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository` | alias of `DbalUnansweredSearches` | yes |
| `Phil\FaqBundle\Domain\Contract\SpentVoteTokenRepository` | alias of `DbalSpentVoteTokens` | yes |
| `phil_faq.markup` | `Phil\Markup\Markup`, or an alias of `markup` | no |
| `phil_faq.throttle.votes`, `phil_faq.throttle.searches` | `SymfonyThrottle` | no |
| `Phil\FaqBundle\Infrastructure\Http\FaqController` | `FaqController` | yes |
| `Phil\FaqBundle\Infrastructure\Http\FaqAdminController` | `FaqAdminController`, with the administration | yes |
| `phil:faq:export`, `phil:faq:import` | `ExportCommand`, `ImportCommand`, with the console and the YAML component | no |
