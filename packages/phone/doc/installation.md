# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-mbstring` | any | `parse()` measures the text it reads, and an error shows it |
| `ext-dom` | any | Reading libphonenumber's XML: the importer only, but Composer requires it with the library |
| `ext-ctype` | any | Checking the digits of the metadata, in the importer |

```sh
composer require phil/phone
```

The numbering plans ship with the library, in `data/`: nothing is downloaded at run time.
