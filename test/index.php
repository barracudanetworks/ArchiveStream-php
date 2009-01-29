<?php

# load zipstream class
require '../zipstream.php';

# get path to current file
$pwd = dirname(__FILE__);

# add some random files
$files = array(
  '../extras/zip-appnote-6.3.1-20070411.txt',
  '../zipstream.php',
);

# create new zip stream object
$zip = new ZipStream('test.zip', array(
  'comment' => 'this is a zip file comment.  hello?'
));

# common file options
$file_opt = array(
  # file creation time (2 hours ago)
  'time'    => time() - 2 * 3600,

  # file comment
  'comment' => 'this is a file comment. hi!',
);

# add files under folder 'asdf'
foreach ($files as $file) {
  # build absolute path and get file data
  $path = ($file[0] == '/') ? $file : "$pwd/$file";
  $data = file_get_contents($path);

  # add file to archive
  $zip->add_file('asdf/' . basename($file), $data, $file_opt);
}

# add same files again wihtout a folder
foreach ($files as $file) {
  # build absolute path and get file data
  $path = ($file[0] == '/') ? $file : "$pwd/$file";
  $data = file_get_contents($path);

  # add file to archive
  $zip->add_file(basename($file), $data, $file_opt);
}

# finish archive
$zip->finish();

?>
