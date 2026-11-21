# Testing

## The bundle's tests

The suite `tests/Unit` tests the value types and the journal row; the catalog, the definitions and the price list; the
prompt templates on Symfony's translator, plain and ICU, with fallbacks; the quota on a real `UsageMeter` in memory —
reservations refused at each meter and given back, settlements above and below the reservation, replays, the person's
own key, the semaphore on Symfony Lock's memory store —; `AiTasks` on a scripted model and a moving clock — keys by
policy, failures given back, refusals, quotas, budgets and the ceiling, the cost bound with and without `max_tokens`,
the places among the tasks running at once, streams read, failed, left or never read, outputs, decisions, totals,
erasure —; one contract for both journals, run in memory and on Doctrine with SQLite in memory, totals included; the
Symfony AI adapter on Symfony AI's mock platform — messages, structured output, tools, images and speech, streams —; the
Replicate adapter on Symfony's mock HTTP client; the notices on the shipped translations, the disclosure, the register
and the privacy module; the secrets adapter on a real `UserSecrets`; and the configuration and wiring. The suite
`tests/Functional` boots a small application with FrameworkBundle and its translator, DoctrineBundle on SQLite, the
secrets bundle and a usage meter, and runs tasks from their prompts to their decision and their quota.

From the monorepo root:

```sh
vendor/bin/phpunit packages/ai-bundle/tests
```

The suite `tests/Integration` runs the journal's contract on Doctrine with a real MySQL 8.4 and PostgreSQL 17, its
totals summed by each server; each server's test reads its URL from `PHIL_TEST_MYSQL_URL` or
`PHIL_TEST_POSTGRESQL_URL` and is skipped without it. It also starts two PHP processes that admit a task at the same
instant on `phil/usage-meter`'s Doctrine DBAL store with room for one — for the tokens, the budget and the ceiling —,
on SQLite in a file and on both servers: one is admitted, the other refused, and one reservation stays counted.

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application that uses it

Keep the journal in memory, and answer in place of the model by replacing the `Completions` port:

```yaml
# config/packages/test/phil_ai.yaml
phil_ai:
    store: memory
```

```yaml
# config/services_test.yaml
services:
    Phil\AiBundle\Application\Port\Completions:
        class: App\Tests\Ai\ScriptedCompletions
        public: true
```

```php
final class ScriptedCompletions implements Completions
{
    public function complete(ModelCall $call, #[\SensitiveParameter] ?string $userKey = null): Completion
    {
        return new Completion('A rewritten passage.', new Tokens(120, 80));
    }

    public function stream(ModelCall $call, #[\SensitiveParameter] ?string $userKey = null): Generator
    {
        yield 'A rewritten ';
        yield 'passage.';

        return new Completion('A rewritten passage.', new Tokens(120, 80));
    }
}
```

Or keep the Symfony AI adapter and give the platform service Symfony AI's own mock,
`Symfony\AI\Platform\Test\MockPlatformFactory::createPlatform()`, which answers scripted results and lets a test read
the messages it received.
