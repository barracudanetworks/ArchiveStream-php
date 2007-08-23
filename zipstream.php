<?php

#
# ZipStream - Streamed, dynamically generated zip archives.
# Paul Duncan <pabs@pablotron.org>
#
# Usage:
#
# Streaming zip archives is a simple, three-step process:
#
# 1.  Create the zip stream:
#
#     $zip = new ZipStream('example.zip');
#
# 2.  Add one or more files to the archive:
#
#     # add first file
#     $data = file_get_contents('some_file.gif');
#     $zip->add_file('some_file.gif', $data);
#
#     # add second file
#     $data = file_get_contents('some_file.gif');
#     $zip->add_file('another_file.png', $data);
#
# 3.  Finish the zip stream:
#
#     $zip->finish();
#
# You can also add an archive comment, add comments to individual files,
# and adjust the timestamp of files.  See the API documentation below
# for additional information.
#
# Example:
#
#   # create a new zip stream object
#   $zip = new ZipStream('some_files.zip');
#
#   # list of local files
#   $files = array('foo.txt', 'bar.jpg');
#
#   # read and add each file to the archive
#   foreach ($files as $path)
#     $zip->add_file($path, file_get_contents($path));
# 
#   # write archive footer to stream
#   $zip->finish();
#
class ZipStream {
  var $opt = array(),
      $files = array(),
      $cdr_ofs = 0,
      $ofs = 0; 

  #
  # Create a new ZipStream object.
  #
  # Parameters:
  #
  #   $name - Name of output file (optional).
  #   $opt  - Hash of archive options (optional, see "Archive Options"
  #           below).
  #
  # Archive Options:
  #
  #   comment             - Comment for this archive.
  #   content_type        - HTTP Content-Type.  Defaults to 'application/x-zip'.
  #   content_disposition - HTTP Content-Disposition.  Defaults to 
  #                         'attachment; filename=\"FILENAME\"', where
  #                         FILENAME is the specified filename.
  #   send_http_headers   - Boolean indicating whether or not to send
  #                         the HTTP headers for this file.
  #
  # Note that content_type and content_disposition do nothing if you are
  # not sending HTTP headers.
  #
  # Examples:
  #
  #   # create a new zip file named 'foo.zip'
  #   $zip = new ZipStream('foo.zip');
  #
  #   # create a new zip file named 'bar.zip' with a comment
  #   $zip = new ZipStream('bar.zip', array(
  #     'comment' => 'this is a comment for the zip file.',
  #   ));
  #
  # Notes:
  #
  # If you do not set a filename, then this library _DOES NOT_ send HTTP
  # headers by default.  This behavior is to allow software to send its
  # own headers (including the filename), and still use this library.
  #
  function ZipStream($name = null, $opt = array()) {
    # save options
    $this->opt = $opt;

    $this->output_name = $name;
    if ($name || $opt['send_http_headers'])
      $this->need_headers = true; 
  }

  #
  # add_file - add a file to the archive
  #
  # Parameters:
  #   
  #  $name - path of file in archive (including directory).
  #  $data - contents of file
  #  $opt  - Hash of options for file (optional, see "File Options"
  #          below).  
  #
  # File Options: 
  #  time     - Last-modified timestamp (seconds since the epoch) of
  #             this file.  Defaults to the current time.
  #  comment  - Comment related to this file.
  #
  # Examples:
  #
  #   # add a file named 'foo.txt'
  #   $data = file_get_contents('foo.txt');
  #   $zip->add_file('foo.txt', $data);
  # 
  #   # add a file named 'bar.jpg' with a comment and a last-modified
  #   # time of two hours ago
  #   $data = file_get_contents('bar.jpg');
  #   $zip->add_file('bar.jpg', $data, array(
  #     'time'    => time() - 2 * 3600,
  #     'comment' => 'this is a comment about bar.jpg',
  #   ));
  # 
  function add_file($name, $data, $opt = array()) {
    # compress data
    $zdata = gzdeflate($data);

    # calculate header attributes
    $crc  = crc32($data);
    $zlen = strlen($zdata);
    $nlen = strlen($name);
    $len  = strlen($data);

    # create dos timestamp
    $opt['time'] = $opt['time'] ? $opt['time'] : time();
    $dts = $this->dostime($opt['time']);

    # build file header
    $fields = array(              # (from V.A of APPNOTE.TXT)
      array('V', 0x04034b50),     # local file header signature
      array('v', 0x14),           # version needed to extract
      array('v', 0x00),           # general purpose bit flag
      array('v', 0x08),           # compresion method (deflate)
      array('V', $dts),           # dos timestamp
      array('V', $crc),           # crc32 of data
      array('V', $zlen),          # compressed data length
      array('V', $len),           # uncompressed data length
      array('v', $nlen),          # filename length
      array('v', 0),              # extra data len
    );

    # pack fields
    $ret = $this->pack_fields($fields) . $name . $zdata;

    # add to central directory record and increment offset
    $this->add_to_cdr($name, $opt, $crc, $zlen, $len, strlen($ret));

    # print data
    $this->send($ret);
  }

  #
  # finish - Write zip footer to stream.
  #
  # Example:
  #
  #   # add a list of files to the archive
  #   $files = array('foo.txt', 'bar.jpg');
  #   foreach ($files as $path)
  #     $zip->add_file($path, file_get_contents($path));
  # 
  #   # write footer to stream
  #   $zip->finish();
  # 
  function finish() {
    $this->add_cdr($this->opt);
    $this->clear();
  }

  ###################
  # PRIVATE METHODS #
  ###################

  function add_to_cdr($name, $opt, $crc, $zlen, $len, $rec_len) {
    $this->files[] = array($name, $opt, $crc, $zlen, $len, $this->ofs);
    $this->ofs += $rec_len;
  }

  function add_cdr_file($args) {
    list ($name, $opt, $crc, $zlen, $len, $ofs) = $args;

    # get attributes
    $comment = $opt['comment'] ? $opt['comment'] : '';

    # get dos timestamp
    $dts = $this->dostime($opt['time']);

    $fields = array(              # (from V,F of APPNOTE.TXT)
      array('V', 0x02014b50),           # central file header signature
      array('v', 0x00),                 # version made by
      array('v', 0x14),                 # version needed to extract
      array('v', 0x00),                 # general purpose bit flag
      array('v', 0x08),                 # compresion method (deflate)
      array('V', $dts),                 # dos timestamp
      array('V', $crc),                 # crc32 of data
      array('V', $zlen),                # compressed data length
      array('V', $len),                 # uncompressed data length
      array('v', strlen($name)),        # filename length
      array('v', 0),                    # extra data len
      array('v', strlen($comment)),     # file comment length
      array('v', 0),                    # disk number start
      array('v', 0),                    # internal file attributes
      array('V', 32),                   # external file attributes
      array('V', $ofs),                 # relative offset of local header
    );

    # pack fields, then append name and comment
    $ret = $this->pack_fields($fields) . $name . $comment;

    $this->send($ret);

    # increment cdr offset
    $this->cdr_ofs += strlen($ret);
  }

  function add_cdr_eof($opt = null) {
    $num = count($this->files);
    $cdr_len = $this->cdr_ofs;
    $cdr_ofs = $this->ofs;

    # grab comment (if specified)
    $comment = '';
    if ($opt && $opt['comment'])
      $comment = $opt['comment'];

    $fields = array(                # (from V,F of APPNOTE.TXT)
      array('V', 0x06054b50),         # end of central file header signature
      array('v', 0x00),               # this disk number
      array('v', 0x00),               # number of disk with cdr
      array('v', $num),               # number of entries in the cdr on this disk
      array('v', $num),               # number of entries in the cdr
      array('V', $cdr_len),           # cdr size
      array('V', $cdr_ofs),           # cdr ofs
      array('v', strlen($comment)),   # zip file comment length
    );

    $ret = $this->pack_fields($fields) . $comment;
    $this->send($ret);
  }

  function add_cdr($opt = null) {
    foreach ($this->files as $file)
      $this->add_cdr_file($file);
    $this->add_cdr_eof($opt);
  }

  function clear() {
    $this->files = array();
    $this->ofs = 0;
    $this->cdr_ofs = 0;
  }

  ###########################
  # PRIVATE UTILITY METHODS #
  ###########################

  function send_http_headers() {
    # grab options
    $opt = $this->opt;
    
    # grab content type from options
    $content_type = 'application/x-zip';
    if ($opt['content_type'])
      $content_type = $this->opt['content_type'];

    # grab content disposition 
    $disposition = 'attachment';
    if ($opt['content_disposition'])
      $disposition = $opt['content_disposition'];

    if ($this->output_name) 
      $disposition .= "; filename=\"{$this->output_name}\"";

    $headers = array(
      'Content-Type'              => $content_type,
      'Content-Disposition'       => $disposition,
      'Pragma'                    => 'public',
      'Cache-Control'             => 'public, must-revalidate',
      'Content-Transfer-Encoding' => 'binary',
    );

    foreach ($headers as $key => $val)
      header("$key: $val");
  }

  function send($str) {
    if ($this->need_headers)
      $this->send_http_headers();
    $this->need_headers = false;

    echo $str;
  }

  function dostime($when = 0) {
    # get date array for timestamp
    $d = getdate($when);

    # set lower-bound on dates
    if ($d['year'] < 1980) {
      $d = array('year' => 1980, 'mon' => 1, 'mday' => 1, 
                 'hours' => 0, 'minutes' => 0, 'seconds' => 0);
    }

    # remove extra years from 1980
    $d['year'] -= 1980;

    # return date string
    return ($d['year'] << 25) | ($d['mon'] << 21) | ($d['mday'] << 16) |
           ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
  }

  function pack_fields($fields) {
    list ($fmt, $args) = array('', array());

    # populate format string and argument list
    foreach ($fields as $field) {
      $fmt .= $field[0];
      $args[] = $field[1];
    }

    # prepend format string to argument list
    array_unshift($args, $fmt);

    # build output string from header and compressed data
    return call_user_func_array('pack', $args);
  }
};

?>
