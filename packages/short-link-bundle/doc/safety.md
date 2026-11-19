# Safety and privacy

## Targets

A short domain that redirects anywhere is an open redirect: anyone holding a link sends visitors where the link says,
under the reputation of the domain. Every target is therefore checked when a link is saved:

- an absolute `http` or `https` URL, read by phil/url as a browser reads it: no IP address in any of its spellings,
  no single name, no user name nor password, no `localhost` unless `allow_localhost` is on; it is kept in its
  canonical form, an international host in ASCII;
- never on a short link domain, so that no link sends to another;
- on a host of `targets.allowed_hosts`, an exact host name — a subdomain is another host —, unless `targets.any_host`
  allows any host. The list is empty by default, so that no link leaves for a host the application did not name;
- with `targets.check`, read through phil/safe-http: an address that is or resolves to a private network, one that
  redirects there, one that does not answer in time or answers with an error status is refused.

The location sent at each visit is written and checked again by the location builder of phil/redirects-bundle.

## The rate limit

Creating a link spends one creation of its author's allowance: 30 an hour by default, on a sliding window. The author
is the signed-in account in the administration, or else the client's address; it is hashed before it names the window,
so the store of the limiter keeps no account name nor address. The allowance is spent only once the link is otherwise
valid: a refused target spends nothing. In the `prod` environment, a cache pool local to each server is refused, since
a client could spend the limit once per server.

## The administration

Every page needs the configured role; every button posts a CSRF token, and the form carries the token of Symfony's
forms. The list reports an outcome only from a closed list, never what a crafted link puts in its query string. The QR
code is served with `Content-Security-Policy: default-src 'none'` and `X-Content-Type-Options: nosniff`.

## What is kept

A link keeps its code, target, label, locale, terms, the count of its visits by people, the moment of the last one, and
the count of its visits by machines. Nothing of the visitor: no address, no user agent, no referrer. The `User-Agent`
and the purpose headers are read to classify the visit, then dropped. The recorders told of a visit receive a
`ShortLinkVisit` — the link's identifier, its code, the host, its locale and the class of its visitor — and nothing
else. The analytics recorder counts the visits of people only, as a server event of phil/analytics-bundle under that
bundle's own rules: the code and the locale become counters only when the site
declares them as dimensions with those values.

## Bounds

Every input is bounded: a code of 64 characters, a target of 2,048, a label of 200, an identifier decoded of 255, a
search of 200, a page number of 100,000. The codec's decoding has a fuzz target.
