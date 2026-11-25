# Log redaction

**State: implemented, tested, and wired from configuration**, as `RedactingProcessor`, tagged
`monolog.processor`.

Logs are redacted **before** they are written, not after: a secret that reaches a file, a log shipper or an
error tracker cannot be taken back. `RedactingProcessor` runs the message, the context and the `extra` fields
of every Monolog record through `ContextRedactor`, which combines three redactors over one `Denylist`.

```text
LogRecord ─► RedactingProcessor ─► ContextRedactor ─┬─ Denylist            (keys)
                                                     ├─ UrlRedactor         (every string)
                                                     └─ CommandLineRedactor (every string)
```

The placeholder is always `[redacted]` (`UrlRedactor::PLACEHOLDER`, also exposed as
`ContextRedactor::PLACEHOLDER`).

## What is redacted

| Input | Result | Rule |
|---|---|---|
| `['password' => 'hunter2']` | `['password' => '[redacted]']` | Denied key, any type, any depth |
| `['email' => 'ada@example.com']` | `['email' => '[redacted]']` | A category of `#[PersonalData]`, denied by default |
| `['token' => ['a' => 1]]` | `['token' => '[redacted]']` | A denied array is not walked |
| `/reset?token=abc&lang=fr` | `/reset?[redacted]` | Every query string, parameter names included |
| `what?now` | `what?[redacted]` | Any `?` followed by non-space characters |
| `redis://app:secret@valkey:6379` | `redis://[redacted]@valkey:6379` | User information of every absolute URL |
| `/p/abc123/edit` (prefix `/p/` declared) | `/p/[redacted]/edit` | Segment after a declared token path prefix |
| `app:import --password=hunter2` | `app:import --password=[redacted]` | Value of a denied long option |
| `app:import -p hunter2` (`p: password` declared) | `app:import -p [redacted]` | Value of a declared short option |
| a `Throwable` under `exception` | an array: class, redacted message, code, file, line, previous | Stack trace dropped |
| any other object, at any depth | its class name, `App\Message\SignUp` | Its properties never reach the formatter |
| a case of an enum | its class and case, `App\Status::Open` | |
| `#frag` in a URL | unchanged | Browsers do not send fragments |

## `Denylist` — keys

`Denylist::of(string ...$keys)` or `Denylist::fromList(array $keys)`; `denies(string $key): bool`;
`keys(): list<string>` returns the normalised keys and patterns, sorted (useful to assert configuration in a
test).

- **Compared by normalised form, exactly.** Case, `_`, `-`, `.` and spaces are dropped on both sides, so
  `totp_secret`, `totpSecret` and `TOTP-Secret` are one key, and an HTTP header matches in any case.
- **Not a substring search.** `code` does not deny `postal_code` or `country_code`: a list that redacts
  everything stops being read.
- **Patterns are explicit and opt-in.** `seed*` (starts with), `*seed` (ends with), `*seed*` (contains). The
  stem is normalised, so `*_seed` and `*seed` are one pattern. A star anywhere else is refused: a pattern is a
  deliberate widening written where it can be read.
- Only **string** keys are compared. Integer keys of a list are never denied; their values are walked.

The list belongs to the application; the bundle decides only how a key is compared.

### The categories of personal data

`log_redaction.personal_data_categories` adds the names of categories of `#[PersonalData]` to the denylist. By
default it holds the categories the PHIL packages declare, `RedactingProcessor::PERSONAL_DATA_CATEGORIES`: `email`,
`person_name`, `nickname`, `phone`, `ip` and `contact`. A context that logs an address under `email`, a name under
`personName` or a number under `Phone` logs `[redacted]` even with an empty `denylist`. Each entry is a category, a
lowercase `snake_case` token, compared as a key: `email` denies `email` and `E-mail`, not `email_count`. An
application lists its own categories there, beside these, and `personal_data_categories: []` turns the rule off.

## `UrlRedactor` — strings

`new UrlRedactor(list<string> $tokenPathPrefixes = [])`; `redact(string $text): string`. Applied to the message
and to every string value at any depth, because a URL logged under `uri`, `referer` or `target` carries its
query string the same way.

- **Query strings, whole.** Parameter names go with values, because a name can be the secret (`?abc123`) and a
  list of "safe" parameters is a list someone forgets to update. The match runs from `?` to whitespace, `#`,
  a quote, `<` or `>`.
- **User information, whole.** From `scheme://` to the last `@` before the host ends, so a password holding an
  `@` is taken whole. The user name goes too: a token passed as a user name is a credential. Database, cache
  and mailer clients quote their DSN in exception messages.
- **Token path segments**, only after a **declared** prefix (see [configuration.md](configuration.md)). A token in
  the path survives query string redaction; the routes that carry one are declared rather than guessed. Every
  declared prefix is matched by one pattern, so the number of prefixes does not multiply the passes over a string.
  Where one declared prefix starts another (`/p/` and `/p/x/`), the token of each goes: `/p/x/abc` becomes
  `/p/[redacted]/[redacted]`, whatever the order they are declared in.
- **A raw query string without its `?`** (`token=abc&x=1`, what `Request::getQueryString()` returns) is not
  recognisable. Put the key it is logged under (`query_string`, `query`) in the denylist.

## `CommandLineRedactor` — command lines in strings

`new CommandLineRedactor(Denylist $denylist, array $shortOptions = [])`; `redact(string $text): string`.
Symfony logs a failed command with its command line in the message and under `command`, a key that is not
denied, so a secret passed as an option would otherwise reach the log twice.

**Long options.** The option name is read as a key by the denylist, patterns included: `--db-password` falls
under `*password*`, `--api-token` under `*token*`.

- `--name=value`: the value is redacted whatever it is, empty included.
- `--name value`: the next word is redacted unless it starts with `--` (then it is another option or the `--`
  that ends options). A word starting with a single `-` **is** redacted: a generated secret can start with a
  dash.
- A value is a shell word: a `"…"` string, or a run of plain characters, `'…'` strings and `\` escapes, so
  `'it'\''s'` is one value. A plain run stops at whitespace and at `"` (where a log message quoting the command
  line closes it). An unterminated quote runs to the end of the text. A value is read in runs and never given
  back, so a value of any length, a megabyte included, is redacted like a short one.
- The name must start an option: `--` at the start of the text, or after whitespace, a quote, `(` or `=`.
- The value of an option that is not denied is kept, and read again for an option it might quote
  (`--message "--password=x"`).

**Short options, through a declared table only.** A letter says nothing about itself — `-p` is `--password` for
one command and `--port` for the next. With `['p' => 'password']` and `password` denied, `-p secret`,
`-psecret`, `-p=secret` and `-vp secret` (the letter closing a set of flags) all lose the value. Letters are
case-sensitive. With no table, which is the default, no short option is read. `re-print` is not read as `-p`.

**Errs towards too much**: `--token --force` keeps `--force`, but `--token release` loses `release` even if
`--token` was a flag, and `--token -v` loses `-v`.

## `ContextRedactor` — structure

`new ContextRedactor(Denylist $denylist, UrlRedactor $urls, array $shortOptions = [])`. It builds its own
`CommandLineRedactor` from the denylist and the short option table.

| Method | Effect |
|---|---|
| `redactMessage(string $message): string` | URL redaction then command line redaction |
| `redactContext(array $context): array` | Walks the array: denied string keys → placeholder; strings → both redactors; arrays → recursed; `Throwable` → redacted array; an enum case → `Class::Case`; any other object → its class name; other scalars and null unchanged |

A `Throwable` becomes:

```php
[
    'class' => RuntimeException::class,
    'message' => 'failed GET /x?[redacted]',   // through both redactors
    'code' => 3,
    'file' => '/app/src/Client.php',
    'line' => 42,
    'previous' => [/* the same shape */] ?? null,
]
```

The exception message is where a failed request's URL most often lands. The trace is dropped because its
arguments are not reachable without serialisation. Any other object becomes its class name: a formatter would
otherwise write its public properties, or what it serialises to, and a message or a DTO of another bundle would
reach the log whole, personal data included. A context that needs a value logs that value, under a key the
denylist can judge.

## Failure mode

If a regular expression fails (for example a PCRE limit lowered by the runtime configuration), the redactor
returns `[redacted]` for the whole string rather than the unredacted text. A long value alone does not make the
command line patterns fail: they read a value in runs, without backtracking, whatever its length.

## `RedactingProcessor`

`Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor` implements Monolog's `ProcessorInterface`.

| Member | |
|---|---|
| `__construct(ContextRedactor $redactor)` | Build from your own redactor |
| `static fromConfiguration(array $denylist, array $tokenPathPrefixes, array $shortOptions = [], array $personalDataCategories = []): self` | Build from plain lists, as the bundle does, the categories denied as keys; throws `InvalidPrivacyValue` on a malformed entry |
| `PERSONAL_DATA_CATEGORIES` | The categories the bundle denies by default |
| `__invoke(LogRecord $record): LogRecord` | Returns `$record->with(message:, context:, extra:)`, all three redacted; channel, level and datetime unchanged |

`extra` is redacted because other processors fill it — a web processor adds the request URI with its query
string.

### Processor order in a Symfony application

The bundle tags the service `monolog.processor` with no attributes: MonologBundle adds it to **every channel
logger**, at priority 0. Keep in mind how Monolog orders processors:

- **Logger processors run before handlers**, and processors pushed onto a handler run inside that handler, after
  them. A handler-level processor (such as the PSR-3 message interpolation MonologBundle configures on a handler)
  therefore sees the already-redacted context — but anything a handler-level processor *adds* is not redacted.
- **Among logger processors, a higher priority runs first.** A processor that fills `extra` must run before the
  redactor; if yours is also at priority 0 or lower, lower the redactor's priority from a compiler pass in your
  kernel, registered to run before MonologBundle's own pass:

```php
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// in Kernel::build(ContainerBuilder $container)
$container->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition(RedactingProcessor::class)
            ->clearTag('monolog.processor')
            ->addTag('monolog.processor', ['priority' => -100]);
    }
}, PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
```

### Without MonologBundle

```php
use Monolog\Logger;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;

$logger = new Logger('app');
$logger->pushHandler($handler);
// Monolog runs the most recently pushed processor first: push the redactor first, then the processors
// that fill `extra`, so that the redactor runs after them.
$logger->pushProcessor(RedactingProcessor::fromConfiguration(['*password*', '*token*'], ['/p/']));
$logger->pushProcessor($webProcessor);
```

## What the processor does not cover

- The content of an object in the context: only its class name is kept.
- Text written outside Monolog: web server access logs, error trackers with their own scrubbing, `var_dump`.
- Secrets that are neither under a denied key, nor in a URL credential or query string, nor the value of a
  denied option: free text such as `"password is hunter2"` in a message is not recognised.
