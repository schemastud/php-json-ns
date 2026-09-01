<?php

/**
 * Framework-free Pest bootstrap — this core has NO illuminate/testbench, so there is no
 * TestCase base class. Fixtures are the concrete example documents from asset 05.
 *
 * @return array<string, mixed>
 */
function fixtureData(string $name): array
{
    $path = __DIR__.'/Fixtures/'.$name.'.json';

    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The raw JSON text of a fixture (for schema fixtures fed to the opis-driven vocab registry,
 * which wants the JSON string, not a decoded array).
 */
function fixtureJson(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/'.$name.'.json');
}
