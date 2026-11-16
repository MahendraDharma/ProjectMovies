<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use ProjectMovies\Registry;

// Mount a local directory as a protocol. Everything written through
// "local://..." lands in this real directory.
$root = sys_get_temp_dir() . '/projectmovies-example-' . getmypid();
Registry::register('local', new Filesystem(new LocalFilesystemAdapter($root)));

// A library that only knows native file functions can now write through
// Flysystem without knowing it exists.
$config = <<<JSON
{"app": "ProjectMovies", "mounted": "local://"}
JSON;
file_put_contents('local://config/app.json', $config);

// ...and the file is really on disk:
echo "Real path: $root/config/app.json\n";
echo file_get_contents('local://config/app.json'), "\n";
