# Data

Every list the library ships was written for it and is under its MIT licence; none is copied from another project.

| File | What it holds |
|---|---|
| `data/dictionaries/passwords.php` | 314 common passwords, the most common first |
| `data/dictionaries/names.php`, `surnames.php` | 307 first names and 135 surnames common in English, French, Spanish and German |
| `data/dictionaries/english.php`, `french.php`, `spanish.php`, `german.php` | About 700 to 1 070 common words each |
| `data/wordlists/en.php`, `fr.php`, `es.php`, `de.php` | The words of the passphrases: lowercase ASCII letters, 3 to 10 of them, each once |
| `translations/phil_passwords.en.php`, `.fr.php` | The warnings and suggestions |
| `translations/validators.en.php`, `.fr.php` | The messages of the constraints |

The rank of an entry is its position in its file, from 1: the order is a judgement of frequency, not a measure. The
keyboard layouts are written in `Strength\KeyboardLayout` as rows of keys.

An application with a list of its own — the name of the site, the words of its domain, passwords seen in its own
incidents — gives it as a `Dictionary`, rather than editing these files.
