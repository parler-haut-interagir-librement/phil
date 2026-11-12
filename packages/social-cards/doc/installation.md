# Installation

```sh
composer require phil/social-cards
```

| Requirement | Used for |
|---|---|
| PHP 8.5 or later, `ext-uri` | The URI extension of PHP, which parses and resolves addresses as browsers do |
| `ext-ctype` | The sizes read in the tags of a page |
| `ext-dom`, `ext-libxml` | The HTML5 parser that reads the pages of other sites |
| `ext-gd`, with FreeType | Drawing the cards and measuring their text |
| `ext-intl` | Breaking the lines of a title by the rules of its language, and its characters |
| `ext-mbstring` | Counting and cutting texts in characters |
| `phil/fonts` | Reading the fonts of a template: their format, family, licence and hash |
| `phil/url` | Checking the addresses read in the pages of other sites: a public host name, never an IP address nor `localhost` |

A Symfony application installs phil/social-cards-bundle, which requires this library.
