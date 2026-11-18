# Acting on behalf

A member may act on behalf of an organization — publish, sign, answer as the organization — while the real author is
recorded beside it:

```php
$post = $onBehalf->act($acme->id, $member, 'post.publish', fn (): Post => $posts->publish($draft, author: $acme->id->value));
```

`act()` checks that the member holds `organization.act_on_behalf` in an active organization, runs the work, then
dispatches `ActedOnBehalf` with the organization, the real author, the action and the moment, for `phil/audit-bundle`
or any listener. Nothing is dispatched when the work throws. The action is 1 to 64 lowercase letters, digits, `_`,
`.` or `-`, starting with a letter.

While the work runs, `current()` gives the context — the organization, the author and the action —, the innermost one
when calls nest, so that code deeper down records the real author too:

```php
$context = $onBehalf->current();     // OnBehalfContext, or null outside act()
```

The context lives for the call only; `reset()`, on `kernel.reset`, clears it between the requests of a worker.
