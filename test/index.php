<?php

# load zipstream class
require '../zipstream.php';

# add some random files
$files = array(
  '/store/dl/wurfl.xml.gz',
  '/store/dl/hugh_hefner_interview.html',
);

$zip = new ZipStream('test.zip', array(
  'comment' => 'this is a zip file comment.  hello?'
));

$file_opt = array(
  'time'    => time() - 2 * 3600,
  'comment' => 'this is a file comment. hi!',
);

# add files under folder 'asdf'
foreach ($files as $file)
  $zip->add_file('asdf/' . basename($file), file_get_contents($file), $file_opt);

# add same files again wihtout a folder
foreach ($files as $file)
  $zip->add_file(basename($file), file_get_contents($file), $file_opt);

$zip->finish();

?>
