# Preview and confirmation

## The preview

```php
$preview = $previewer->preview($user, SelectionToken::fromString($token), $changes, new DateTimeImmutable());
```

The preview reads the selection without using the token, asks the right to run the action again, checks the change
against the action's fields and counts the items. It changes nothing, so a GET may show it.

| Property or method | What it gives |
|---|---|
| `count` | the items the selection names now, before the voters |
| `sample` | up to `Limits::sampleSize` items, each a `SampleItem` with its label, `granted`, and one `ValueChange` per field |
| `deniedInSample()` | how many sampled items the voter refuses |
| `destructive` | whether the action warns |
| `changes` | the change set shown |
| `expiresAt` | when the token stops working |

`ValueChange::changes()` tells a field that takes another value from one that already has it.

## The dialog

The confirmation is a native `<dialog>`, modal with a script and shown in the page without one. The form inside it
posts the token and the change again; nothing in the page names an item.

```twig
<dialog id="bulk-confirm" open aria-labelledby="bulk-confirm-title" {{ stimulus_controller('dialog') }}>
    <form method="post" action="{{ path('admin_bulk_confirm') }}">
        <h2 id="bulk-confirm-title">{{ 'bulk.confirm.title'|trans({count: preview.count}) }}</h2>

        {% if preview.destructive %}
            <p role="alert">{{ 'bulk.confirm.destructive'|trans }}</p>
        {% endif %}

        <table>
            <caption>{{ 'bulk.confirm.sample'|trans({shown: preview.sample|length, count: preview.count}) }}</caption>
            {% for item in preview.sample %}
                {% for change in item.changes %}
                    <tr>
                        <th scope="row">{{ item.label }}</th>
                        <td>{{ change.field }}</td>
                        <td>{{ change.old ?? '—' }}</td>
                        <td>{{ change.new ?? '—' }}</td>
                    </tr>
                {% endfor %}
            {% endfor %}
        </table>

        {% if preview.deniedInSample() > 0 %}
            <p>{{ 'bulk.confirm.denied'|trans({count: preview.deniedInSample()}) }}</p>
        {% endif %}

        <input type="hidden" name="token" value="{{ token }}">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token('bulk_actions') }}">
        <input type="hidden" name="changes" value="{{ preview.changes.values|json_encode }}">

        <button type="submit" value="confirm">{{ 'bulk.confirm.submit'|trans }}</button>
        <button type="submit" formmethod="dialog" formnovalidate>{{ 'bulk.confirm.cancel'|trans }}</button>
    </form>
</dialog>
```

The change travels as JSON, which keeps a `null`, a `false` or a `0` as it is, where a hidden field would turn each
into a string.

The Stimulus controller only calls `showModal()` on connect and gives the focus back to the button that opened it on
close; without it, the `open` attribute shows the dialog in the page.

## The confirmation

```php
#[Route('/admin/bulk/confirm', name: 'admin_bulk_confirm', methods: ['POST'])]
public function confirm(Request $request, BulkConfirmer $confirmer): Response
{
    $this->guard->check($request, 'bulk_actions');      // stateless CSRF and origin, below

    $changes = json_decode($request->request->getString('changes'), true, 4, JSON_THROW_ON_ERROR);

    $run = $confirmer->confirm(
        $this->getUser()->getUserIdentifier(),
        SelectionToken::fromString($request->request->getString('token')),
        ChangeSet::of(\is_array($changes) ? $changes : []),
        new DateTimeImmutable(),
    );

    return $this->redirectToRoute('admin_bulk_report', ['run' => $run->id->value], Response::HTTP_SEE_OTHER);
}
```

`confirm()` reads the selection first, so another person's attempt does not use up the owner's token, then takes it in
one atomic step: of two confirmations of the same token, one gets it. It asks the right again, checks the change and
the limits again, files the run, records `AuditEvent::Confirmed` and dispatches the first chunk. Nothing changes in the
request itself.

`BulkConfirmer::start()` makes the same checks without a token, for a caller that showed its own confirmation; see
[Bridges](bridges.md).

## The requests a page sends

| Request | Method | Effect |
|---|---|---|
| Selection | POST, CSRF and origin checked | files the selection, redirects to the preview with the token |
| Preview | GET | none |
| Confirmation | POST, CSRF and origin checked | files the run and dispatches it |
| Report | GET | none |
| Retry | POST, CSRF and origin checked | makes the failed items pending and dispatches the run |

Every POST checks Symfony's stateless CSRF token — the identifier `bulk_actions` listed under
`framework.csrf_protection.stateless_token_ids` — and the browser's `Sec-Fetch-Site`, `Origin` or `Referer`, which must
name the site. A GET changes nothing, so a link, a prefetch or a crawler never runs an action. The token travels in the
form's body or the address of the preview; it is single-use and short-lived, and it is useless to anyone but its owner.
