<!-- phil-dev source-sha256=9a3b040cb50f987e392dc862b00dfe2e623a0ea8bccec54111d3df8dd515e815 -->
# Subdivisions

```php
Subdivisions::of('CA');                     // the provinces and territories
Subdivisions::of('BR', 'AC');               // the cities of Acre
Subdivisions::find('Québec', 'CA');         // a Subdivision, or null
```

Une `Subdivision` a un identifiant parmi ses sœurs, le `code` que porte une adresse — `QC`, ou le nom là où le pays n'a
pas de codes —, un `localCode`, un `name` et un `localName` dans la langue de sa `locale` — `fr` pour celles du Canada
—, un code ISO lorsque les données en ont un, le motif par lequel commencent ses codes postaux, et l'indication qu'elle
a des enfants ou non.

`find()` trouve une valeur par identifiant, code ou nom, locaux compris, sans égard à la casse : `qc`, `Quebec` et
`Québec` désignent la même province. `keys()` donne ce par quoi une subdivision se trouve, en minuscules. Chaque liste
est indexée par ces clés la première fois qu'une valeur y est cherchée : une recherche parmi les 853 villes du Minas
Gerais coûte autant qu'une recherche parmi les 13 provinces du Canada. Lorsque deux subdivisions d'une liste partagent
une clé, c'est la première qui est trouvée. `nameIn($locale)` donne le nom local dans sa langue, sinon le nom.

Les listes sous le premier niveau sont nommées comme commerceguys/addressing les nomme — `BR-AC`, ou une empreinte des
parents plus bas — et se trouvent par les identifiants des parents.
