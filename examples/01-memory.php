<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use ProjectMovies\Registry;

// Register an in-memory Flysystem filesystem as the "mem://" protocol.
Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));

// From here on, native PHP file functions just work on mem:// paths.
file_put_contents('mem://greeting.txt', "Hello from ProjectMovies!\n");

$handle = fopen('mem://greeting.txt', 'ab');
assert($handle !== false);
fwrite($handle, "Appended through a stream handle.\n");
fclose($handle);

echo file_get_contents('mem://greeting.txt');

mkdir('mem://reports/2026', 0777, true);
file_put_contents('mem://reports/2026/q1.csv', "month,revenue\njan,100\n");

foreach (scandir('mem://reports/2026') as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    printf("%s is_file=%s size=%d\n", $entry, is_file('mem://reports/2026/' . $entry) ? 'yes' : 'no', filesize('mem://reports/2026/' . $entry));
}

rename('mem://greeting.txt', 'mem://renamed.txt');
unlink('mem://renamed.txt');

var_export(file_exists('mem://renamed.txt')); // false
echo PHP_EOL;
