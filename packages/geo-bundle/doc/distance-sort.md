# Sorting by distance

The `distance-sort` controller sorts places by their distance to the visitor **in the browser**. The visitor's
position is asked of the browser — which asks the visitor — and never leaves it: the server publishes the places,
blurred if their exact position is private, and knows nothing of who is near which.

```twig
<div data-controller="phil--geo-bundle--distance-sort"
     data-phil--geo-bundle--distance-sort-locate-value="false"
     data-phil--geo-bundle--distance-sort-locale-value="{{ app.request.locale }}">
    <button data-action="phil--geo-bundle--distance-sort#locate">Nearest first</button>
    <ul>
        {% for store in stores %}
            <li {{ geo_attributes(store.position, 3) }}>{{ store.name }} <span data-distance></span></li>
        {% endfor %}
    </ul>
</div>
```

| Value | Default | Meaning |
|---|---|---|
| `latitude`, `longitude` | none | A fixed origin: the page sorts from it at once |
| `locate` | `false` | Asks the visitor's position as the page connects |
| `system` | `metric` | `metric` or `imperial` |
| `locale` | the browser's | The locale distances are written in: a BCP 47 tag, or a Symfony locale such as `fr_CA`, read as `fr-CA` |

- Each `item` target carries `data-latitude` and `data-longitude`; items are reordered within their parent, nearest
  first, and those without a position go last, in their order.
- An element of the item with `data-distance` receives the distance, `2,7 km`.
- The `locate` action asks the position on demand — a button is kinder than asking as the page loads.
- The controller dispatches `sorted`, with the origin in `detail.origin`, and `denied` when the browser gives no
  position; listen to the latter to explain why the list stayed as it was.

The distance is the same great-circle distance as `Coordinates::distanceTo()`, in `assets/src/distance.js`.
