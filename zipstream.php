<?php

#
# ZipStream - Streamed, dynamically generated zip archives.
# Paul Duncan <pabs@pablotron.org>
#
# Usage:
#
#   # create a new zip stream object
#   $zs = new ZipStream();
#
#   # list of local files
#   $files = array('foo.txt', 'bar.jpg');
#
#   # read and add each file to the archive
#   foreach ($files as $path)
#     $zs->add_file($path, file_get_contents($path));
# 
#   # write archive footer to stream
#   $zs->finish();
#
class ZipStream {
  var $files = array(),
      $cdr_ofs = 0,
      $ofs = 0; 

  function ZipStream($name = 'untitled.zip', $send_http_headers = false) {
    $this->output_name = $name;
    $this->need_headers = $send_http_headers; 
  }

  #
  # add_file - add a file to the archive
  #
  # Parameters:
  #   
  #  $name - path of file in archive (including directory).
  #  $data - contents of file
  #  $time - last-modified timestamp of file (optional)
  #
  # Example:
  #
  #   $data = file_get_contents('foo.txt');
  #   $zs->add_file('foo.txt', $data);
  # 
  function add_file($name, $data, $time = 0) {
    # compress data
    # $zdata = substr(gzcompress($data), 2, -4);
    $zdata = gzdeflate($data);

    # calculate header attributes
    $crc  = crc32($data);
    $zlen = strlen($zdata);
    $nlen = strlen($name);
    $len  = strlen($data);

    # create dos timestamp
    $time = $time ? $time : time();
    $dts = $this->dostime($time);

    # build file header
    $fields = array(              # (from V.A of APPNOTE.TXT)
      array('V', 0x04034b50),     # local file header signature
      array('v', 0x14),           # version needed to extract
      array('v', 0x00),           # general purpose bit flag
      array('v', 0x08),           # compresion method (deflate)
      array('V', $dts),           # dos timestamp
      # array('v', 0x00),           # file mod time (dos) FIXME
      # array('v', 0x00),           # file mod date (dos) FIXME
      array('V', $crc),           # crc32 of data
      array('V', $zlen),          # compressed data length
      array('V', $len),           # uncompressed data length
      array('v', $nlen),          # filename length
      array('v', 0),              # extra data len
    );

    # pack fields
    $ret = $this->pack_fields($fields) . $name . $zdata;

    # add to central directory record and increment offset
    $this->add_to_cdr($name, $time, $crc, $zlen, $len, strlen($ret));

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
  #     $zs->add_file($path, file_get_contents($path));
  # 
  #   # write footer to stream
  #   $zs->finish();
  # 
  function finish($opt = null) {
    $this->add_cdr($opt);
    $this->clear();
  }

  function send_http_headers($name = 'untitled.zip') {
    $headers = array(
      'Content-Type'              => 'application/x-zip',
      'Content-Disposition'       => "attachment; filename=\"{$name}\"",
      'Pragma'                    => 'public',
      'Cache-Control'             => 'public, must-revalidate',
      'Content-Transfer-Encoding' => 'binary',
    );

    foreach ($headers as $key => $val)
      header("$key: $val");
  }

  ###################
  # PRIVATE METHODS #
  ###################

  function add_to_cdr($name, $time, $crc, $zlen, $len, $rec_len) {
    $this->files[] = array($name, $time, $crc, $zlen, $len, $this->ofs);
    $this->ofs += $rec_len;
  }

  function add_cdr_file($args) {
    list ($name, $time, $crc, $zlen, $len, $ofs) = $args;
    $dts = $this->dostime($time);

    $fields = array(              # (from V,F of APPNOTE.TXT)
      array('V', 0x02014b50),     # central file header signature
      array('v', 0x00),           # version made by
      array('v', 0x14),           # version needed to extract
      array('v', 0x00),           # general purpose bit flag
      array('v', 0x08),           # compresion method (deflate)
      array('V', $dts),           # dos timestamp
      # array('v', 0x00),           # file mod time (dos) FIXME
      # array('v', 0x00),           # file mod date (dos) FIXME
      array('V', $crc),           # crc32 of data
      array('V', $zlen),          # compressed data length
      array('V', $len),           # uncompressed data length
      array('v', strlen($name)),  # filename length
      array('v', 0),              # extra data len
      array('v', 0),              # file comment length
      array('v', 0),              # disk number start
      array('v', 0),              # internal file attributes
      array('V', 32),             # external file attributes
      array('V', $ofs),           # relative offset of local header
    );

    # pack fields and append name
    $ret = $this->pack_fields($fields) . $name;

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

  function send($str) {
    if ($this->need_headers)
      $this->send_http_headers($this->output_name);
    $this->need_headers = false;

    echo $str;
  }

  function dostime($when = 0) {
    $d = getdate($when);
    if ($d['year'] < 1980)
      $d = array('year' => 1980, 'mon' => 1, 'mday' => 1, 'hours' => 0, 'minutes' => 0, 'seconds' => 0);

    return ($d['year'] << 25) | ($d['mon'] << 21) | ($d['mday'] << 16) |
           ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1;
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
