# Syntax

`EmailAddress::parse(string $input, SyntaxPolicy $policy = new SyntaxPolicy())` returns an `EmailAddress` or throws
`Exception\InvalidEmailAddress`, whose `reason` is a `SyntaxError`. The message of the exception never holds the
address: it is personal data, and messages reach logs.

## What is accepted

The mailbox of RFC 5321, what a mail server can deliver to:

- spaces, tabs and line breaks around the address are dropped; nothing else is repaired;
- before the @, a dot-atom: letters, digits and ``! # $ % & ' * + / = ? ^ _ ` { | } ~ -``, dots between them, never
  first, last or doubled; at most 64 octets;
- after the @, a domain name of at least two labels, each at most 63 octets in ASCII, the whole at most 253, the last
  label neither numeric nor a single character;
- the whole address at most 254 octets with its domain in ASCII.

The domain goes through IDNA (UTS #46, nontransitional processing, STD3 rules, bidi and joiner checks), as browsers
read it: `ada@BÜCHER.example` is `ada@bücher.example`, sent as `ada@xn--bcher-kva.example`. A domain written in
punycode is checked and shown in its script.

## The policy

`SyntaxPolicy` holds what the default refuses. Each `with` method returns a new policy.

| Method | Allows | Why it is refused by default |
|---|---|---|
| `withInternationalLocalPart()` | Non-ASCII characters before the @ (RFC 6531), normalised to NFC | The sender and every server on the way must speak SMTPUTF8 |
| `withQuotedLocalPart()` | `"ada lovelace"@example.com`, with `\"` and `\\` | Few servers and fewer people use them; they hide spaces and @ |
| `withIpLiteral()` | `ada@[192.0.2.1]`, `ada@[IPv6:2001:db8::1]` | No public mailbox lives there |

## The forms of an address

| Member | Gives |
|---|---|
| `localPart` | The part before the @, as written: it is case-sensitive |
| `domain` | The domain in lowercase, in its script |
| `asciiDomain` | The domain in ASCII, for SMTP and the DNS |
| `toString()`, `(string)` | `localPart@domain`, for people |
| `toAscii()` | `localPart@asciiDomain`, for a mail transport |
| `requiresSmtpUtf8()` | Whether the part before the @ is not ASCII |
| `isIpLiteral()` | Whether the domain is an IP address in brackets |
| `equals()` | The same part before the @ and the same domain |

Two addresses that differ only by the case of the part before the @ are different addresses: RFC 5321 leaves the
choice to the receiving server. An application that wants one account per mailbox whatever the case compares a key of
its own.

## Reasons

| `SyntaxError` | When |
|---|---|
| `Empty`, `TooLong`, `MissingAt` | Nothing, more than 254 octets, no @ |
| `EmptyLocalPart`, `LocalPartTooLong` | Nothing before the @, more than 64 octets |
| `MisplacedDot` | A dot first, last or doubled before the @ |
| `InvalidLocalPart` | A character no address holds, broken UTF-8, a malformed quoted part |
| `InternationalLocalPart` | A non-ASCII character the policy does not allow |
| `QuotedLocalPart` | A quoted part the policy does not allow |
| `EmptyDomain`, `InvalidDomain` | Nothing after the @, a name IDNA refuses or with an empty label |
| `DomainTooLong`, `LabelTooLong` | More than 253 octets, a label of more than 63, in ASCII |
| `SingleLabelDomain`, `InvalidTopLevelDomain` | No dot; a numeric or one-character last label |
| `IpLiteral`, `InvalidIpLiteral` | An IP literal the policy does not allow, or one that is malformed |

`describe()` gives each in a few English words for a log; a form translates the case's value instead.
