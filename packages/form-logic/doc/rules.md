# The rules

A rule is a JsonLogic document: a constant, a list, or an object holding one operation, `{"operator": [operands]}`. An
operation whose operands are not a list takes the value as its only operand: `{"!": true}` reads as `{"!": [true]}`.
The library reads a strict subset of JsonLogic and refuses everything else when the rule is read, never skipping it.

## The operators

| Operator | Operands | Value |
|---|---|---|
| `var` | a name, or a name and a default | The value of the answer under that name; the default, else `null`, when it has none |
| `missing` | 1 or more names | The names that have no value — absent, `null` or `""` —, as a list |
| `missing_some` | a count, then a list of names | `[]` once that many names have a value, else the names that have none |
| `==`, `!=` | 2 | Equal or not, the types converted as JavaScript's `==` converts them |
| `===`, `!==` | 2 | Equal and of the same type, or not |
| `<`, `<=` | 2, or 3 | Before (or equal); with three operands, the middle one within the bounds |
| `>`, `>=` | 2 | After (or equal) |
| `!` | 1 | Whether the operand is false |
| `!!` | 1 | Whether the operand is true |
| `and` | 1 or more | The first operand that is false, else the last one |
| `or` | 1 or more | The first operand that is true, else the last one |
| `if` | a condition and a value, repeated, then an optional value | The value of the first condition that is true, else the last value, else `null` |
| `in` | a needle and a list or a text | Whether the list holds an item strictly equal to the needle, or the text holds the needle written as text |
| `+`, `*` | 1 or more | The sum, the product |
| `-` | 1 or 2 | The difference, or the opposite of one operand |
| `/`, `%` | 2 | The quotient, the remainder of the sign of the first operand |
| `min`, `max` | 1 or more | The smallest, the largest |

A name is 1 to 8 segments of letters, digits and `_`, joined by dots, the first starting with a letter or `_`, each
up to 64 characters. A dotted name reads into a map, `guest.name`, or a list, `days.0`.

## The values

A value is `null`, `true` or `false`, a number, a text, or a list of values. A number is a double, as in JavaScript:
an integral number up to 2^53 is kept as an integer, a larger one as the nearest double, `-0` as `0`. In the answer,
an object, a map at the end of a name, a non-finite number or anything else reads as `null`.

`null`, `false`, `0`, `""` and `[]` are false; every other value is true, `"0"` and `[0]` included.

## The conversions

The comparisons convert their operands as JavaScript does, with one exception: a text is read as a number in decimal
notation only.

- **A text as a number.** ASCII spaces around it are ignored; then an optional sign, digits with an optional decimal
  point, and an optional exponent: `" 12 "`, `"-.5"`, `"1e3"`. An empty text, or one of spaces only, is `0`. Any other
  text — `"0x10"`, `"Infinity"`, `"12px"`, `"1_000"` — is not a number, and compares neither equal, before nor after
  anything.
- **Other values as numbers.** `null` and `false` are `0`, `true` is `1`, a list is read as its text.
- **A value as text.** `null`, `true`, `false`; a number in its shortest form, in exponent notation from 10^21 and
  below 10^-6 (`1e+21`, `1e-7`); a list as its items joined by commas, `null` items left empty.
- **`==`.** `null` equals only `null`. A boolean is compared as its number; a number and a text, as numbers; a list
  and a scalar, as the list's text; two lists are never equal.
- **`<` and the other orderings.** Two texts are ordered by their UTF-16 code units, as JavaScript sorts them, so a
  day written `YYYY-MM-DD` orders by time; any other pair is compared as numbers.
- **Arithmetic.** Each operand is read as a number. A result that is not a finite number — a text that is no number,
  a division or a remainder by zero, an overflow — is `null`.

These rules are the same in PHP and in the browser; [Testing](testing.md#the-shared-cases) runs one suite of cases
through both, and the test cases JsonLogic publishes too, each difference listed with its reason
([Testing](testing.md#the-cases-jsonlogic-publishes)).

## The limits

`JsonLogicReader` takes a `Limits`:

| Limit | Default | |
|---|---|---|
| `maxBytes` | 65,536 | Bytes of the JSON |
| `maxDepth` | 32 | Operations nested in each other |
| `maxNodes` | 1,000 | Constants, variables, lists and operations |
| `maxListItems` | 500 | Items of a list, names of a `missing` |
| `maxTextLength` | 10,000 | Characters of a text, which is valid UTF-8 |

Within them, a rule is read, checked and evaluated in time proportional to its size: no operator loops, and each node
is evaluated once at most.

## What is refused

- Any operator outside the subset, among them `cat`, `substr`, `merge`, `map`, `filter`, `reduce`, `all`, `none`,
  `some`, `log`, `method` and `?:`; an object of several keys, or of none.
- `{"var": ""}`, which would read the whole answer, a variable named by a number, a name that does not match the
  pattern above, a `var` of more than two operands.
- An operation with the wrong number of operands; `missing` names that are not texts; a `missing_some` count that is
  not an integer from 0 to the number of names.
- A non-finite number; a rule over the limits; invalid JSON.

A refused rule throws `Phil\FormLogic\Exception\InvalidLogic`, whose message names the first problem.
