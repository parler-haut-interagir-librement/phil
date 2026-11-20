<?php

declare(strict_types=1);

use Phil\CommerceProductTemplateBundle\Application\PrintProduction;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The white layer printed under the colours of a dark garment.
        'words' => ['underbase'],
    ],
    'fuzz' => [
        // The dice of draws and the elements of print layouts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'dice-code' => 'tests/Fuzz/dice-code.php',
            'print-element' => 'tests/Fuzz/print-element.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside the fuzzer, which gives them what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // Les ports suivent le reste du commerce : dépôts dans Domain\Repository, comme Arkitect l’exige, et ports
            // d’application dans Application\Port, comme ceux de phil/commerce-bundle.
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/Port/ImageStore.php',
                    'src/Application/Port/PrintFonts.php',
                    'src/Application/Port/ProductionStore.php',
                    'src/Domain/Repository/ProductDesignRepository.php',
                    'src/Domain/Repository/ProductGroupRepository.php',
                    'src/Domain/Repository/ProductTemplateRepository.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // Huit octets aléatoires ou sept, ou neuf : le nom du fichier temporaire n’a qu’à différer de celui d’un
            // autre écrivain, ce qu’aucun test ne voit sans deux écritures simultanées. Le nom d’un fichier
            // d’impression prend 32 caractères d’un condensat de 64, dès le premier ou le deuxième : il en a 32 des deux façons.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$temporary = \\\\sprintf\\(.*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$temporary = \\\\sprintf\\(.*', '.*JSON_PRESERVE_ZERO_FRACTION\\)\\), 0, 32\\);'],
            ],
            // Équivalents : des listes que seuls des foreach lisent et dont personne n’indexe les clés.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    // Les éléments retenus ne sont lus que par des foreach, jamais par un index.
                    '.*\\$applying = array_values\\(array_filter.*',
                    // Les lignes de production sont ajoutées une à une à une liste : leurs clés ne servent pas.
                    '.*return array_values\\(\\$units\\);',
                    // Les clés du tableau des axes ne sont pas lues : la ligne du CSV n’écrit que les valeurs.
                    '.*\\.\\.\\.array_values\\(\\$axes\\).*',
                ],
            ],
            // Les valeurs d’une option choisie et celles de ses tirages s’excluent : une valeur choisie n’a jamais
            // de tirage, et un tirage n’a jamais de valeur choisie.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\\$unitOptions\\[\\$code\\] = \\$value->value \\?\\? \\$value->draws.*'],
            ],
            // Équivalents : la valeur est déjà une chaîne ou un entier que array_map, appelée en mode faible, convertit.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    // Un élément de dés a toujours son option : les autres genres sortent avant de la lire.
                    '.*\\$option = \\(string\\) \\$element->option;',
                    // Un élément image a toujours son chemin.
                    '.*PrintElementKind::Image => .*\\(string\\) \\$element->path.*',
                    // La fonction de cellule est rappelée par array_map, en mode faible : un entier devient une chaîne.
                    '.*\\$row = \\[\\$this->orderNumber, \\$line->sku, \\(string\\) \\$line->quantity\\];',
                    // Le chemin d’une police est celui, réel, d’un fichier que TypeFace vient de lire : hash_file en rend toujours un condensat.
                    '.*\\(string\\) hash_file.*',
                ],
            ],
            // Un élément image a toujours son chemin, un chiffre de l’alphabet validé a toujours sa position, et un
            // entier est un flottant de la même valeur dans le type qu’on lui demande.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) strpos\\(self::ALPHABET.*'],
            ],
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => ['.*\\(float\\) \\$value : throw.*'],
            ],
            // Équivalents : sans retour, une zone sans couche n’a ni encre ni chemin et rend la même liste vide ; une
            // encre répétée écrit deux fois le même chemin ; qu’un fichier soit marqué écrit par true ou par false,
            // isset() le voit ; le nom d’une couche n’est lu que par le compositeur, qui n’en exige que l’unicité ou rien.
            'ReturnRemoval' => [
                'ignore' => [PrintProduction::class.'::zoneFiles'],
            ],
            'UnwrapArrayUnique' => [
                'ignore' => [PrintProduction::class.'::zoneFiles'],
            ],
            'TrueValue' => [
                'ignore' => [PrintProduction::class.'::zoneFiles'],
            ],
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['.*new Layer\\(.layer-.*'],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*new Layer\\(.layer-.*'],
            ],
            // Équivalents du condensat d’un fichier : le genre d’un objet se déduit de ses autres champs (une image n’a
            // pas de texte), l’encre est dans le nom du fichier, et les drapeaux de json_encode ne changent que
            // l’écriture d’un flottant entier ou l’erreur d’une valeur que le module n’écrit jamais.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$item\\[.kind.\\], \\$item\\[.box.\\]->x.*',
                    '.*\\$description\\[\\] = \\[\\$layer\\[.ink.\\], \\$items\\];',
                ],
            ],
            'BitwiseOr' => [
                'ignoreSourceCodeByRegex' => ['.*JSON_PRESERVE_ZERO_FRACTION\\)\\), 0, 32\\);'],
            ],
            // Équivalents : le résultat de trim() n’a pas de saut de ligne final, que le $ sans D verrait seul, et
            // les objets de la ligne de production sont écrits en JSON par leurs propriétés publiques, qui portent
            // les noms mêmes de toArray().
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$numbers = 1 === preg_match.*',
                    '.*preg_match\\(.\\/\\^\\[1-9A-Ka-k\\]\\+\\$\\/D.*',
                ],
            ],
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*.lines. => array_map\\(.*'],
            ],
        ],
    ],
];
