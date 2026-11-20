# Outputs, streaming and tools

A task asks the model for one of four outputs, set by its `output`: `text`, the default; `structured`, data shaped by
a class; `image`; or `speech`. Whatever it asks for, the answer comes back as a `Suggestion`, journaled and charged
the same way, and nothing is applied until the application says so.

## Structured output

```yaml
phil_ai:
    tasks:
        character.invent:
            platform: main
            model: 'the-model'
            output: structured
            structure: 'App\Ai\Character'
```

The task sends its class through Symfony AI's `response_format` option. Symfony AI's structured output subscriber,
registered on the platform's event dispatcher as the Symfony AI bundle does, turns the class into a JSON schema for
the provider and the answer into an object: the suggestion's `data` holds it, and `text` is empty. A platform without
that subscriber passes the option as it is to the provider.

```php
$suggestion = $tasks->run(new TaskRequest('character.invent', $project, $locale, ['setting' => 'a harbour town']));

$character = $suggestion->data;    // an App\Ai\Character, shown in a form before anything is saved
```

`structure` is required for `structured` and refused for the other outputs.

## Tools the model proposes

```yaml
phil_ai:
    tasks:
        rules.answer:
            platform: main
            model: 'the-model'
            tools:
                look_up:
                    description: 'Looks a rule up in the game system, by its name.'
                    parameters: { type: object, properties: { rule: { type: string, description: 'The rule' } }, required: [rule], additionalProperties: false }
```

The tools go to the model as Symfony AI tools: a name of 1 to 64 letters, digits, `_` or `-`, a description, and the
JSON schema of the arguments. **The bundle never runs a tool.** A call the model proposes comes back, not run, in the
suggestion's `toolCalls`, a list of `ProposedToolCall` — the provider's `id`, the tool's `name` and the `arguments` as
the model wrote them, unchecked against the schema. The application shows what the model wants to do, runs it only once
a person agrees, and checks the arguments as it checks any input. A task that generates an image or speech takes no
tools.

Running the tools and handing their results back to the model in a loop is the work of an agent — Symfony AI's
`symfony/ai-agent` and its toolbox — which an application builds on a platform of its own when it wants one.

## Images and speech

An `image` or `speech` task sends its user prompt alone, as text-to-image and text-to-speech models take it, and reads
the bytes the platform returns: the suggestion's `media` is a `Media`, its `bytes` and its `mimeType`, and
`toDataUri()` gives a `data:` address for an `<img>` or an `<audio>` shown before the person decides. The bytes live
only in the suggestion; the application stores what the person accepts, with `phil/media-bundle` for instance.

A model that charges by the image or the request, whatever its tokens, takes a price per call:

```yaml
phil_ai:
    prices:
        'black-forest-labs/flux-schnell': { input: 0, output: 0, per_call: 3000 }    # 0.003 per image
```

The platforms are adapters, chosen by configuration:

| Adapter | What it generates | Through |
|---|---|---|
| Hugging Face | Images, with a text-to-image model | Symfony AI's Hugging Face bridge, `symfony/ai-hugging-face-platform` |
| Replicate | Images, by a model's name | This bundle's `Infrastructure\Replicate\ReplicatePlatformFactory` |
| A text-to-speech model | Speech | The Symfony AI bridge of the provider that offers it |

Symfony AI's own Replicate bridge serves language models; for images, `ReplicatePlatformFactory` builds a Symfony AI
platform — a `Provider` of the bundle's model client and result converter — on Replicate's predictions API. It is
registered when an HTTP client is installed, and builds the platform on the application's token, or on a person's own
as their `user_key_factory`:

```yaml
services:
    app.replicate_platform:
        class: Symfony\AI\Platform\PlatformInterface
        factory: ['@Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory', 'create']
        arguments: ['%env(REPLICATE_API_TOKEN)%']

phil_ai:
    platforms:
        images:
            platform: 'app.replicate_platform'
            user_key_factory: 'Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory'
            secret: 'replicate-token'
    tasks:
        cover.draw:
            platform: images
            model: 'black-forest-labs/flux-schnell'
            output: image
            options: { aspect_ratio: '2:3' }
```

The model is named `owner/name`; the task's options join the prompt in the model's input. The request asks Replicate
to wait for the prediction, 60 seconds at most; a prediction that has not succeeded by then, failed or was cancelled
fails the task. The image is fetched from Replicate's delivery network only, over HTTPS, without following a
redirect, and refused past 20 MiB: an address elsewhere in an answer never sends the server anywhere else.

## Streaming

```php
$stream = $tasks->stream(new TaskRequest('draft.rewrite', $project, $locale, ['text' => $passage]));

foreach ($stream as $piece) {
    echo $piece;    // or send it as a server-sent event, a Turbo stream, a Mercure update
    flush();
}

$suggestion = $stream->suggestion();    // once the last piece is read
```

`stream()` checks everything `run()` checks before it returns — the task, the prompt, the key, the quota — then asks
the platform for a stream with Symfony AI's `stream` option. Each piece is text to show at once; the tool calls the
model proposes while streaming come back in the suggestion. The whole suggestion, its tokens from the platform's
`token_usage` metadata, is journaled and charged once the last piece is read, as `run()` does it.

A stream is read once. A failure while reading throws `TaskFailed`; a stream left before its end, or never read, is
journaled as failed: what was shown was never a whole proposal. Either way the task is given back to the quota.
