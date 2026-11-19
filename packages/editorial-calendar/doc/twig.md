# Showing a view with Twig

A `Period` holds everything a template needs: its days, each with its local date and its entries in order, and each
entry with its kind, its moment in UTC, its items, its batch or its transition. The package writes no HTML: the
application's controller builds the view and its template draws it, with the Card, Badge and Empty recipes of the PHIL
UX kit once the Symfony UX Toolkit has installed them.

```php
use Phil\EditorialCalendar\Filter;
use Phil\EditorialCalendar\ItemState;

$filter = Filter::all();

if ($request->query->has('section')) {
    $filter = $filter->bySections($request->query->getString('section'));
}

return $this->render('editorial/week.html.twig', [
    'week' => $view->week($calendar, $date, $zone, filter: $filter),
    'zone' => $zone->getName(),
]);
```

```twig
{# templates/editorial/week.html.twig #}
{% for day in week.days %}
    <twig:Card>
        <twig:Card:Header>
            <twig:Card:Title>{{ day.from|format_date('full', timezone: zone) }}</twig:Card:Title>
        </twig:Card:Header>
        <twig:Card:Content>
            {% for entry in day.entries %}
                <p>
                    <time datetime="{{ entry.at|date('c', false) }}">{{ entry.at|format_time('short', timezone: zone) }}</time>
                    {% if entry.kind.value == 'batch' %}
                        <twig:Badge variant="secondary">{{ entry.batch.title }}</twig:Badge>
                        {{ entry.items|map(item => item.title)|join(', ') }}
                    {% elseif entry.kind.value == 'item' %}
                        {{ entry.items|first.title }}
                        <twig:Badge variant="outline">{{ entry.items|first.state.value }}</twig:Badge>
                    {% else %}
                        {{ entry.transition.transition }} {{ entry.transition.resource.toString }}
                    {% endif %}
                </p>
            {% else %}
                <twig:Empty>
                    <twig:Empty:Title>Nothing planned</twig:Empty:Title>
                </twig:Empty>
            {% endfor %}
        </twig:Card:Content>
    </twig:Card>
{% endfor %}
```

`format_date` and `format_time` come from `twig/intl-extra`. Moments are written with `date('c', false)`, which keeps
them in UTC for the `datetime` attribute, and shown in the reader's time zone by `format_date` and `format_time`: the
`from` of a day is its local midnight in that zone.

The filters are plain links or a form sent by `GET`: the page is drawn again on the server, and needs no JavaScript.
A Stimulus controller of the application is only needed for what the server cannot do, such as moving an item by
dragging it, which then calls the application's own route to `reschedule()` it.
