<?php

/**
 * Dumps the seeding command's data tables as JSON, so the sources document
 * is built from the same arrays the seeder uses.
 *
 * The alternative was to type the clubs, notices and events out twice - once
 * for the database and once for the PDF - which is exactly how a document
 * ends up describing data the system does not have.
 *
 *     php gen_sources.php ../src/app/Console/Commands/SeedUiuData.php
 *
 * The constants are read out of the file rather than through the class,
 * because loading the class would need Laravel's autoloader and a whole
 * framework just to read six arrays.
 */

$path = $argv[1] ?? __DIR__.'/../src/app/Console/Commands/SeedUiuData.php';

if (! is_file($path)) {
    fwrite(STDERR, "No such file: {$path}\n");
    exit(1);
}

$source = file_get_contents($path);
$wanted = ['CLUBS', 'NOTICES', 'EVENTS', 'UPCOMING', 'TRIMESTER', 'DEPARTMENTS'];
$out = [];

foreach ($wanted as $name) {
    // Everything between "const NAME = [" and the "];" that closes it at the
    // same indent. The arrays are hand-written and consistently indented, so
    // the closing marker is unambiguous.
    $start = strpos($source, "const {$name} = [");

    if ($start === false) {
        fwrite(STDERR, "Constant {$name} not found.\n");
        exit(1);
    }

    $open = strpos($source, '[', $start);
    $end = strpos($source, "\n    ];", $open);

    if ($end === false) {
        fwrite(STDERR, "Could not find the end of {$name}.\n");
        exit(1);
    }

    $literal = substr($source, $open, $end - $open + strlen("\n    ]"));

    $value = eval("return {$literal};");

    if (! is_array($value)) {
        fwrite(STDERR, "{$name} did not evaluate to an array.\n");
        exit(1);
    }

    $out[$name] = $value;
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
