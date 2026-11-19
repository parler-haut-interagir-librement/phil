<!-- phil-dev source-sha256=9002be4360496b7ed834770f0a1ac6efbf3861167134a1e5cab8624c59ee4f83 -->
# Montrer une vue avec Twig

Une `Period` contient tout ce dont un gabarit a besoin : ses jours, chacun avec sa date locale et ses entrées dans
l'ordre, et chaque entrée avec son genre, son moment en UTC, ses éléments, son lot ou sa transition. Le paquet n'écrit
aucun HTML : le contrôleur de l'application construit la vue et son gabarit la dessine, avec les recettes Card, Badge
et Empty du kit PHIL UX une fois que le Symfony UX Toolkit les a installées.

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

`format_date` et `format_time` viennent de `twig/intl-extra`. Les moments sont écrits avec `date('c', false)`, qui les
garde en UTC pour l'attribut `datetime`, et montrés dans le fuseau horaire du lecteur par `format_date` et
`format_time` : le `from` d'un jour est son minuit local dans ce fuseau.

Les filtres sont de simples liens ou un formulaire envoyé par `GET` : la page est dessinée de nouveau sur le serveur,
et n'a besoin d'aucun JavaScript. Un contrôleur Stimulus de l'application n'est nécessaire que pour ce que le serveur ne
peut pas faire, comme déplacer un élément en le faisant glisser, ce qui appelle alors la route propre à l'application
pour le replanifier avec `reschedule()`.
