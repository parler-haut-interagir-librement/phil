# phil/ai-bundle

**Language-model tasks that propose and never impose, counted to the token, and forgetful of every word.**

phil/ai-bundle runs the language-model tasks of a Symfony application on Symfony AI. A task has a kind —
`draft.rewrite`, `character.describe` — whose prompts are translation messages, rendered in the locale the person
reads; it runs on a platform and a model of the configuration, on the application's key or on the person's own key
kept by `phil/user-secrets-bundle`; the subject it is charged to — an account, a tenant, a project — must have room in
its quotas and budget of `phil/usage-meter` before it starts, and is charged with the tokens and the cost it spent
after; a call that fails is given back. A task asks for text, streamed or whole, data shaped by a class, an image or
speech, and may offer the model tools to propose. What the model answers comes back as a `Suggestion`: nothing is
applied, and no tool is run, until the application accepts it, as proposed or changed by the person, or rejects it. A
journal keeps each task's kind, subject, platform, model, whose key paid, tokens, cost, duration and decision — and
never the prompt, the answer, nor anything the person wrote.

It is for Symfony applications that put a model beside a person's work: a writing tool that rewrites, proofreads or
translates a passage, a game master's assistant that invents a character, a place or a cover image, a free tier
counted in tokens and a paid one with a monthly budget, or members who bring their own key. It tells people they are
talking to an AI, discloses what a suggestion made, lists the application's uses of AI, and answers privacy requests
through `phil/privacy-bundle`.

It is a bundle built on `symfony/ai-platform`, Doctrine ORM, `phil/usage-meter`, `phil/creation-disclosure`,
`phil/url`, Symfony's translation and HTTP client contracts and PSR-20; `phil/user-secrets-bundle` and
`phil/privacy-bundle` are optional. Symfony AI does the talking to the providers; the bundle adds what an application
needs around each call, and a Replicate image adapter that Symfony AI does not offer.

The design follows the layers of the monorepo: `Domain` holds the journal row `JournalEntry`, its value types, its
totals and the port of the journal; `Application` holds `AiTasks`, the catalog of tasks, the prompt templates, the
price list, the quota, `Suggestion` and `SuggestionStream`, the notices, the usage register and the ports
`Completions` and `UserKeys`; `Infrastructure` holds the Symfony AI adapter, the Replicate image platform, the adapter
of `phil/user-secrets-bundle`, the privacy module, the Doctrine and in-memory journals and the service wiring.

## Status

| Part | State |
|---|---|
| Tasks by kind on Symfony AI platforms, with their model and options | Implemented and tested |
| Prompt templates as translation messages, plain or ICU, with the translator's fallbacks | Implemented and tested |
| Suggestions accepted, accepted after edits, or rejected by the application | Implemented and tested |
| A journal of kind, output, subject, platform, model, key, tokens, cost, duration and decision, without content | Implemented and tested |
| Costs from a price list per model and per call, totals summed in SQL by subject and span, erasure by subject | Implemented and tested, on SQLite, MySQL 8.4 and PostgreSQL 17 |
| Quotas of tokens and of tasks, budgets of cost and an application-wide ceiling through `phil/usage-meter`; a failed call given back | Implemented and tested |
| Streamed answers, structured output, tool calls proposed and never run, through Symfony AI | Implemented and tested |
| Images and speech: Hugging Face and text-to-speech through Symfony AI's bridges, Replicate images through the bundle's adapter | Implemented and tested |
| Notices that a person talks to an AI, the disclosure of what a suggestion made through `phil/creation-disclosure`, the usage register | Implemented and tested |
| The journal's module of `phil/privacy-bundle`: export and erasure | Implemented and tested |
| The person's own key through `phil/user-secrets-bundle`, by policy | Implemented and tested |
| Doctrine storage, and a journal in memory for tests | Implemented and tested |
| An MCP server | Not part of the bundle: `phil/mcp-bundle` serves one |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, a platform, the table. |
| [Configuration](doc/configuration.md) | Every option of `phil_ai`. |
| [Tasks and suggestions](doc/tasks.md) | Defining tasks, writing their prompts, running them, deciding on what they propose. |
| [Outputs, streaming and tools](doc/outputs.md) | Structured output, proposed tool calls, images and speech, the Replicate adapter, streaming. |
| [Keys and quotas](doc/keys-and-quotas.md) | Whose key a task runs on, the person's own key, the quotas of tokens and tasks, budgets and the ceiling. |
| [The journal](doc/journal.md) | What is recorded and what never is, costs, totals, erasure. |
| [Transparency and privacy](doc/transparency.md) | The notices, the disclosure of what a suggestion made, the usage register, privacy requests. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, and when. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application that uses it. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/ai-bundle symfony/ai-platform
```

```yaml
# config/packages/phil_ai.yaml
phil_ai:
    platforms:
        main: { platform: 'app.ai_platform' }    # a Symfony AI PlatformInterface service
    prices:
        the-model: { input: 3000000, output: 15000000 }    # micros per million tokens
    tasks:
        draft.rewrite: { platform: main, model: 'the-model', options: { max_tokens: 1024 } }
```

```yaml
# translations/phil_ai.en.yaml
draft.rewrite.system: 'You are a careful editor. Keep the author''s voice.'
draft.rewrite.user: 'Rewrite this passage: {text}'
```

```php
$suggestion = $tasks->run(new TaskRequest('draft.rewrite', $project->reference(), $locale, ['text' => $passage], $member->reference()));

// Show $suggestion->text; then, on the person's answer:
$tasks->accept($suggestion->id);        // or acceptEdited(), or reject()
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Symfony AI Platform 0.15, Doctrine ORM 3.7. `symfony/http-client` for the Replicate
adapter, `phil/user-secrets-bundle` for people's own keys, `phil/privacy-bundle` for privacy requests — each only when
used.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
