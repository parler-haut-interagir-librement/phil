# Testing

## The bundle's tests

From the monorepo root:

```sh
vendor/bin/phpunit packages/paged-media-bundle/tests
```

`tests/Unit` tests `PagedMedia`, the queue, the handler, the purge of the jobs of failed messages, the storage, the job store and the codec against a Gotenberg
client on `MockHttpClient` (`FakeGotenberg`), with a storage, a job store, a message bus and an event dispatcher that
keep what they receive; the queue's tests check that a message holds the job's identifier and nothing else. `tests/Functional` boots a small
application — FrameworkBundle, its HTTP client answered by `GotenbergResponses`, Messenger handling messages at once —
and checks the services, the configuration's refusals, the files of the messages kept in a directory, their jobs
removed, and the job of a failed message forgotten or, with `remove_failed: false`, kept. The bundle is held to
100 % covered mutation score by Infection.

## Testing an application that uses it

Answer Gotenberg with Symfony's mock HTTP client:

```yaml
# config/packages/test/framework.yaml
framework:
    http_client:
        mock_response_factory: App\Tests\GotenbergResponses
```

Or replace `PdfStorage` and `JobStore` with services of your own, and route the messages to the `sync://` transport,
to assert on the files and the jobs kept. A template is a plain class: build it in a unit test and compare the rendered PDF with a reference file,
since the native writer gives the same bytes for the same calls.
