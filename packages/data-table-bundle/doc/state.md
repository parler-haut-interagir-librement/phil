# State in the address

| Parameter | Meaning |
|---|---|
| `sort` | A sortable column, `-` first when descending |
| `page` | The page, from 1 |
| `size` | One of the sizes offered |
| `filters[...]` | The filters, by name |
| `columns[]` | The visible columns, all of them when absent |
| `selected[]` | The selected rows |
| `confirm`, `row` | The action whose question is shown, and its row |
| `edit`, `row` | The column being edited, and its row |

`StateResolver` checks each value against the declaration, and an unknown value falls back on the table's default. Nothing
in the address reaches a query as it was written.

The other parameters of the page are kept in the table's links and forms. When a page holds several tables, give each a
`urlPrefix`: its parameters become `items_sort`, `items_page` and so on.

## Without JavaScript

Every control works as plain HTML:

- sorting, the page size, the filters and the columns belong to a GET form, and the heading buttons submit it;
- the pagination, the Edit links and the row actions are links to the same table in another state;
- the question of an action and the control of a cell are forms that POST to the routes of the bundle, then come back with
  a `303`.

A link is a query string alone, so it works from the page and from the component's own route.
