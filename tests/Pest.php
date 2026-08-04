<?php

/**
 * Framework-free Pest bootstrap — this core has NO illuminate/testbench, so there is no
 * TestCase base class. Fixtures are the concrete example documents from asset 05.
 *
 * @return array<string, mixed>
 */
function fixture(string $name): array
{
    $path = __DIR__.'/Fixtures/'.$name.'.json';

    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}
