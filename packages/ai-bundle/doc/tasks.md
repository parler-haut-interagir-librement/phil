# Tasks and suggestions

## A task is a kind

A task kind names what the task does — `draft.rewrite`, `draft.proofread`, `character.describe` — in up to 64
lowercase letters, digits, `_`, `.` or `-`, starting with a letter. The configuration gives each kind its platform,
model, options and token reserve — and, for a task that asks for more than text, its output, its structure and its
tools ([Outputs, streaming and tools](outputs.md)), and the purpose the usage register states
([Transparency and privacy](transparency.md)):

```yaml
phil_ai:
    tasks:
        draft.rewrite: { platform: main, model: 'the-model', options: { max_tokens: 1024 } }
        character.describe: { platform: main, model: 'the-small-model', options: { temperature: 0.9 } }
```

## Prompts are translations

The prompts of a kind are two messages of the translation domain `phil_ai`: `<kind>.system`, the instructions,
optional, and `<kind>.user`, the request. They are read in the locale the task asks for, with the translator's
fallbacks, and the task's parameters fill the placeholders written `{name}`:

```yaml
# translations/phil_ai.en.yaml
draft.rewrite.system: 'You are a careful editor. Keep the author''s voice and the {genre} tone.'
draft.rewrite.user: 'Rewrite this passage: {text}'
```

```yaml
# translations/phil_ai.fr.yaml
draft.rewrite.user: 'Réécris ce passage : {text}'
```

Here the French request falls back to the English instructions. An ICU catalogue (`phil_ai+intl-icu.en.yaml`) takes
plurals and selects: `Invent {count, plural, one {one name} other {# names}}.` A kind without a user prompt in the
locale nor its fallbacks fails with `MissingPrompt` before anything is counted or called.

Writing prompts as translations keeps them where translators already work, versioned with the application, and lets
a model be asked in the language its answer is wanted in.

## Running a task

```php
use Phil\AiBundle\Application\AiTasks;
use Phil\AiBundle\Application\TaskRequest;

$suggestion = $tasks->run(new TaskRequest(
    kind: 'draft.rewrite',
    subject: 'project-42',             // whom the task is charged to
    locale: $request->getLocale(),
    parameters: ['genre' => 'fantasy', 'text' => $passage],
    owner: 'member-7',                 // the person it runs for; null for a task of the application
));
```

`run()` renders the prompts, picks the key, checks the quota, calls the model, writes the journal and returns a
`Suggestion`; `stream()` does the same with the answer in pieces ([Streaming](outputs.md#streaming)):

| Property | Meaning |
|---|---|
| `id` | The task's identifier in the journal, a UUID v7 |
| `kind`, `subject` | What was asked, and whom it is charged to |
| `text` | What the model wrote; empty for structured data, an image or speech |
| `output` | What the task asked for: `text`, `structured`, `image` or `speech` |
| `data` | The structured output, an object of the task's class or an array; `null` otherwise |
| `toolCalls` | The tools the model proposes to call, as `ProposedToolCall`, none of them run |
| `media` | The generated image or speech, as `Media`; `null` otherwise |
| `tokens` | Tokens read and written, as the platform counted them |
| `cost` | The cost in micros of the configured currency |
| `keySource` | `application` or `user` |
| `proposedAt` | When the task started |

## Propose, then validate

Nothing a task says is applied by the bundle, and no tool it proposes is run. The application shows the suggestion —
beside the passage, as a diff, as a draft the person may edit — and tells the bundle what became of it:

```php
$tasks->accept($id);         // applied as proposed
$tasks->acceptEdited($id);   // applied after the person changed it
$tasks->reject($id);         // set aside
```

A suggestion is decided once; a second decision fails with `AlreadyDecided`, and an identifier the journal does not
hold with `UnknownSuggestion`. The decisions add up in the journal's totals — how often suggestions are kept, changed
or set aside — without a word of them.

The text, the data, the tool calls and the media of a suggestion live only in the `Suggestion`: the application keeps
them in its own draft, its session or its form between two requests, and decides by the identifier.
`Suggestion::disclosure()` then says how the work that takes it was made ([Disclosure](transparency.md#disclosure-of-what-was-made)).

## Failures

When the platform fails — refused, rate-limited, unreachable — the task is journaled as failed, with no tokens, the
task it consumed of the subject's quota is given back, and `TaskFailed` is thrown with the provider's exception as its
previous one. Its message names the task and the class of
the provider's exception, never the provider's message, which may quote the prompt.
