# Formats

## The CSV read

`CsvRowSource::fromString()` and `CsvRowSource::fromFile()` read CSV as RFC 4180 writes it:

- cells quoted or not, a quote doubled inside a quoted cell, line breaks inside it;
- records ended by CRLF, LF or CR, a last record with or without its line end;
- the byte order mark dropped;
- the first record that is not blank taken as the header, its cells trimmed;
- each record keeping the **physical line** it starts on, the header's line and blank lines counted, so that a
  refused row is found in a spreadsheet by its line;
- a cell with text after its closing quote kept as written, its row refused as malformed.

The `CsvDialect` sets the delimiter — a comma, a semicolon, a tab or a bar —, the quote, `"` or `'`, and the encoding:
UTF-8, Windows-1252 or ISO-8859-1, converted to UTF-8. When no dialect is given, the delimiter is the one the first
line holds the most of, a comma when none, and the encoding UTF-8. A file with a byte order mark is UTF-8 whatever the
dialect says; a file that is not valid UTF-8 is refused, with the advice to save it as CSV UTF-8 or to declare its
encoding.

`SourceLimits` bounds what is read: 20 MiB, 100,000 rows after the header and 256 cells a row by default. A file
beyond them, a quote left open or a file without header is refused whole with `SourceRefused`, before any row is
planned; `fromFile()` never reads more than the limit and one byte.

## The CSV written

`CsvWriter` writes UTF-8 with CRLF, a byte order mark first so that a spreadsheet reads the accents, and quotes a cell
that holds the delimiter, the quote, a line break or a space at either end. A cell that a spreadsheet would run as a
formula — starting with `=`, `+`, `-`, `@`, a tab or a carriage return, and not a number — is written after an
apostrophe, and `CsvRowSource` drops that apostrophe when the file comes back. `guardFormulas: false` turns both off.

`TemplateFile::csv()` writes the header of each column of a profile, and `TemplateFile::filename()` the name to offer
it under, `<type>-template.csv`. `RejectedRowsFile` is described in [The dry run](dry-run.md#the-file-of-rejected-rows).

## The formats of a learning platform

`LmsProfiles` holds three profiles. Their templates write the column names below; the other headers listed are
recognised too, folded as [Profiles](profiles.md#the-mapping) says. `LmsProfiles::all()` lists them in the order they
are imported: the units, the people, then the enrolments.

### People

Type `person`, natural key `email`.

| Column | Type | Checks | Also recognised |
|---|---|---|---|
| `email` | email | required, 254 | e-mail, email address, mail, courriel, adresse courriel |
| `given_name` | text | required, 100 | first name, firstname, given name, prénom |
| `family_name` | text | required, 100 | last name, lastname, surname, family name, nom, nom de famille |
| `external_id` | text | 64 | id, identifier, employee number, student number, matricule, identifiant |
| `locale` | text | 35 | language, langue |
| `unit_code` | text | 64, a reference to `organisational-unit` | unit, department, unité, service, code unité |

### Enrolments

Type `enrolment`, natural key `person_email` and `course_code`; `ends_on` is never before `starts_on`.

| Column | Type | Checks | Also recognised |
|---|---|---|---|
| `person_email` | email | required, 254, a reference to `person` | email, e-mail, courriel, learner email |
| `course_code` | text | required, 64, a reference to `course` | course, cours, code cours, code du cours |
| `role` | text | `learner`, `instructor` or `observer` | rôle |
| `starts_on` | date | | start, start date, début, date de début |
| `ends_on` | date | | end, end date, fin, date de fin |

The courses are not imported by these formats: the target answers whether a course code exists.

### Organisational units

Type `organisational-unit`, natural key `code`.

| Column | Type | Checks | Also recognised |
|---|---|---|---|
| `code` | text | required, 64 | unit code, code unité, id |
| `name` | text | required, 200 | unit name, unit, nom, unité |
| `parent_code` | text | 64, a reference to `organisational-unit` | parent, parent unit, code parent, unité parente |

A unit without a parent is at the root. A parent is found in the same file, above or below, or among the units already
known; the plan writes every parent before its children.

```csv
code,name,parent_code
sci-math,Mathematics,sci
sci,Sciences,
sci-math-alg,Algebra,sci-math
```

### Questions

The questions of a quiz and of a survey have profiles of their own, in the packages that own them, and share the
conventions of [Question files](question-files.md).
