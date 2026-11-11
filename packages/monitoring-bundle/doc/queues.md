# Messenger queues

## The page

Import `config/routes/queues.php` behind the application's firewall. The page shows:

- every transport Messenger knows, with the messages it holds — `not counted` for a transport that cannot count —, the
  failure transports marked;
- for each failure transport, its first failed messages, up to `queues.failed_limit`: the id, the class of the message,
  the class of the last error, the transport it failed on, its retries, and when it last failed.

Neither the content of a message nor the text of its error is read: either may carry personal data. Use
`bin/console messenger:failed:show <id>` to look into one message, where the console's access applies.

## Its actions

Each failed message has two forms, posted with a CSRF token of the id `phil_monitoring_queues`:

- **Retry** dispatches the message again, to the transport it failed on, and removes it from the failure transport;
- **Remove** removes it from the failure transport.

Both redirect to the page with `303 See Other`. A transport that is not a failure transport, or a message that is not
there, answers `404`; a missing or wrong token, `400`. With stateless CSRF tokens, add the id to
`framework.csrf_protection.stateless_token_ids`.

## Who sees it

The page and its actions ask the authorization checker for `queues.role`, `ROLE_ADMIN` by default. A user without it
meets the firewall's answer to a denied page: a login page for an anonymous visitor, `403` for a user.

The page is served only when the application has a Messenger bus, Twig, the router, the authorization checker and the
CSRF token manager; without one of them, `MonitoringPass` removes its controller, and its routes, if imported, find
none. `queues.enabled: false` removes the page and the queues altogether.

## Its layout

The page extends `queues.layout`, which defines the blocks `title` and `content`. The bundle's own layout is a plain
page; give the layout of the application's back office to have the page inside it, with its navigation and its styles.
