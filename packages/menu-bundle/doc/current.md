# The current item

`Menus` marks, after the decorators, the item the visitor is on — `current` — and the items that lead to it —
`currentAncestor`.

An item is current when:

- it has a route, the visitor's route is the same, and each of its parameters is among the visitor's with the same
  value as a text — the visitor may have more parameters; or
- it has an address on the site, without a host, whose path is the visitor's path — the query is not compared.

An address on another site is never current. Twig reads the route, its parameters, the path and the language from
the request being handled.
