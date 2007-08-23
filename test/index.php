<?php

require '../zipstream.php';

$files = array(
  '/home/pabs/pablotron/files/url_scripts-0.1.0.tar.gz',
  '/home/pabs/pablotron/files/url_scripts-0.1.1.tar.gz',
  '/home/pabs/pablotron/files/url_scripts-0.1.2.tar.gz',
  '/home/pabs/pablotron/files/url_scripts-0.1.3.tar.gz',
);

$zip = new ZipStream('url_scripts.zip', true);

foreach ($files as $file)
  $zip->add_file(basename($file), file_get_contents($file));

$zip->finish();

?>
