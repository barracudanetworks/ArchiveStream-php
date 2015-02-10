<?php

require_once(__DIR__ . '/stream.php');

class ArchiveStream_ZipNoGmp extends ArchiveStream
{
	// options array
	public $opt = array();

	// files tracked for cdr
	private $files = array();

	// length of the CDR
	private $cdr_len = 0;

	// offset of the CDR
	private $cdr_ofs = 0;

	// will hold both the uncompressed and compressed length
	private $len = null;
	private $zlen = null;

	// version zip created by / must be opened by (4.5 for zip64 support)
	const VERSION = 45;

	/**
	 * Create a new ArchiveStream_Zip object.
	 *
	 * @see ArchiveStream for documentation
	 * @access public
	 */
	public function __construct()
	{
		$this->opt['content_type'] = 'application/x-zip';
		call_user_func_array(array('parent', '__construct'), func_get_args());
	}

	/**
	 * Initialize a file stream
	 *
	 * @param string $name file path or just name
	 * @param int $size size in bytes of the file
	 * @param array $opt array containing time / type (optional)
	 * @param int $meth method of compression to use (defaults to store)
	 * @access public
	 */
	public function init_file_stream_transfer($name, $size, $opt = array(), $meth = 0x00)
	{
		// if we're using a container directory, prepend it to the filename
		if ($this->use_container_dir)
		{
			// the container directory will end with a '/' so ensure the filename doesn't start with one
			$name = $this->container_dir_name . preg_replace('/^\\/+/', '', $name);
		}
		
		$algo = 'crc32b';

		// calculate header attributes
		$this->len = 0;
		$this->zlen = 0;
		$this->hash_ctx = hash_init($algo);

		// Send file header
		$this->add_stream_file_header($name, $size, $opt, $meth);
	}

	/**
	 * Stream the next part of the current file stream.
	 *
	 * @param $data raw data to send
	 * @param bool $single_part used to determin if we can compress
	 * @access public
	 */
	public function stream_file_part($data, $single_part = false)
	{
		$this->len = strlen($data) + $this->len;
		hash_update($this->hash_ctx, $data);

		if ($single_part === true && isset($this->meth_str) && $this->meth_str == 'deflate')
		{
			$data = gzdeflate($data);
		}

		$this->zlen = strlen($data) + $this->zlen;

		// send data
		$this->send($data);
	}

	/**
	 * Complete the current file stream (zip64 format).
	 *
	 * @access private
	 */
	public function complete_file_stream()
	{
		$crc = hexdec(hash_final($this->hash_ctx));

		// build data descriptor
		$fields = array(                // (from V.A of APPNOTE.TXT)
			array('V', 0x08074b50),     // data descriptor
			array('V', $crc),           // crc32 of data
		);

		// convert the 64 bit ints to 2 32bit ints
		list($zlen_low, $zlen_high) = $this->int64_split($this->zlen);
		list($len_low, $len_high) = $this->int64_split($this->len);

		$fields_len = array(
			array('V', $zlen_low),      // compressed data length (low)
			array('V', $zlen_high),     // compressed data length (high)
			array('V', $len_low),       // uncompressed data length (low)
			array('V', $len_high),      // uncompressed data length (high)
		);

		// pack fields and calculate "total" length
		$ret = $this->pack_fields($fields) . $this->pack_fields($fields_len);

		// print header and filename
		$this->send($ret);

		// Update cdr for file record
		$this->current_file_stream[3] = $crc;
		$this->current_file_stream[4] = strval($this->zlen);
		$this->current_file_stream[5] = strval($this->len);
		$this->current_file_stream[6] += strval(strlen($ret) + $this->zlen);
		ksort($this->current_file_stream);

		// Add to cdr and increment offset - can't call directly because we pass an array of params
		call_user_func_array(array($this, 'add_to_cdr'), $this->current_file_stream);
	}

	/**
	 * Finish an archive
	 *
	 * @access public
	 */
	public function finish()
	{
		// adds an error log file if we've been tracking errors
		$this->add_error_log();

		// add trailing cdr record
		$this->add_cdr($this->opt);
		$this->clear();
	}

	/*******************
	 * PRIVATE METHODS *
	 *******************/

	/**
	 * Add initial headers for file stream
	 *
	 * @param string $name file path or just name
	 * @param int $size size in bytes of the file
	 * @param array $opt array containing time
	 * @param int $meth method of compression to use
	 */
	protected function add_stream_file_header($name, $size, $opt, $meth)
	{
		// strip leading slashes from file name
		// (fixes bug in windows archive viewer)
		$name = preg_replace('/^\\/+/', '', $name);
		$extra = pack('vVVVV', 1, 0, 0, 0, 0);

		// create dos timestamp
		$opt['time'] = isset($opt['time']) ? $opt['time'] : time();
		$dts = $this->dostime($opt['time']);

		// Sets bit 3, which means CRC-32, uncompressed and compresed length
		// are put in the data descriptor following the data. This gives us time
		// to figure out the correct sizes, etc.
		$genb = 0x08;

		// build file header
		$fields = array(                // (from V.A of APPNOTE.TXT)
			array('V', 0x04034b50),     // local file header signature
			array('v', self::VERSION),  // version needed to extract
			array('v', $genb),          // general purpose bit flag
			array('v', $meth),          // compresion method (deflate or store)
			array('V', $dts),           // dos timestamp
			array('V', 0x00),           // crc32 of data (0x00 because bit 3 set in $genb)
			array('V', 0xFFFFFFFF),     // compressed data length
			array('V', 0xFFFFFFFF),     // uncompressed data length
			array('v', strlen($name)),  // filename length
			array('v', strlen($extra)), // extra data len
		);

		// pack fields and calculate "total" length
		$ret = $this->pack_fields($fields);

		// print header and filename
		$this->send($ret . $name . $extra);

		// Keep track of data for central directory record
		$this->current_file_stream = array(
			$name,
			$opt,
			$meth,
			// 3-5 will be filled in by complete_file_stream()
			6 => (strlen($ret) + strlen($name) + strlen($extra)),
			7 => $genb,
		);
	}

	/**
	 * Save file attributes for trailing CDR record
	 *
	 * @param string $name path / name of the file
	 * @param array $opt array containing time
	 * @param int $meth method of compression to use
	 * @param string $crc computed checksum of the file
	 * @param int $zlen compressed size
	 * @param int $len uncompressed size
	 * @param int $rec_size size of the record
	 * @param int $genb general purpose bit flag
	 * @access private
	 */
	private function add_to_cdr($name, $opt, $meth, $crc, $zlen, $len, $rec_len, $genb = 0)
	{
		$this->files[] = array($name, $opt, $meth, $crc, $zlen, $len, $this->cdr_ofs, $genb);
		$this->cdr_ofs += $rec_len;
	}

	/**
	 * Send CDR record for specified file (zip64 format).
	 *
	 * @param array $args array of args
	 * @see add_to_cdr() for details of the args
	 * @access private
	 */
	private function add_cdr_file($args)
	{
		list($name, $opt, $meth, $crc, $zlen, $len, $ofs, $genb) = $args;

		// convert the 64 bit ints to 2 32bit ints
		list($zlen_low, $zlen_high) = $this->int64_split($zlen);
		list($len_low, $len_high)   = $this->int64_split($len);
		list($ofs_low, $ofs_high)   = $this->int64_split($ofs);

		// ZIP64, necessary for files over 4GB (incl. entire archive size)
		$extra_zip64 = '';
		$extra_zip64 .= pack('VV', $len_low, $len_high);
		$extra_zip64 .= pack('VV', $zlen_low, $zlen_high);
		$extra_zip64 .= pack('VV', $ofs_low, $ofs_high);

		$extra = pack('vv', 1, strlen($extra_zip64)) . $extra_zip64;

		// get attributes
		$comment = isset($opt['comment']) ? $opt['comment'] : '';

		// get dos timestamp
		$dts = $this->dostime($opt['time']);

		$fields = array(                      // (from V,F of APPNOTE.TXT)
			array('V', 0x02014b50),           // central file header signature
			array('v', self::VERSION),        // version made by
			array('v', self::VERSION),        // version needed to extract
			array('v', $genb),                // general purpose bit flag
			array('v', $meth),                // compresion method (deflate or store)
			array('V', $dts),                 // dos timestamp
			array('V', $crc),                 // crc32 of data
			array('V', 0xFFFFFFFF),           // compressed data length (zip64 - look in extra)
			array('V', 0xFFFFFFFF),           // uncompressed data length (zip64 - look in extra)
			array('v', strlen($name)),        // filename length
			array('v', strlen($extra)),       // extra data len
			array('v', strlen($comment)),     // file comment length
			array('v', 0),                    // disk number start
			array('v', 0),                    // internal file attributes
			array('V', 32),                   // external file attributes
			array('V', 0xFFFFFFFF),           // relative offset of local header (zip64 - look in extra)
		);

		// pack fields, then append name and comment
		$ret = $this->pack_fields($fields) . $name . $extra . $comment;

		$this->send($ret);

		// increment cdr offset
		$this->cdr_len += strlen($ret);
	}

	/**
	 * Adds Zip64 end of central directory record
	 *
	 * @param int $cdr_start the offset where the cdr starts
	 * @access private
	 */
	private function add_cdr_eof_zip64()
	{
		$num = count($this->files);

		list($num_low, $num_high) = $this->int64_split($num);
		list($cdr_len_low, $cdr_len_high) = $this->int64_split($this->cdr_len);
		list($cdr_ofs_low, $cdr_ofs_high) = $this->int64_split($this->cdr_ofs);

		$fields = array(                    // (from V,F of APPNOTE.TXT)
			array('V', 0x06064b50),         // zip64 end of central directory signature
			array('V', 44),                 // size of zip64 end of central directory record (low) minus 12 bytes
			array('V', 0),                  // size of zip64 end of central directory record (high)
			array('v', self::VERSION),      // version made by
			array('v', self::VERSION),      // version needed to extract
			array('V', 0x0000),             // this disk number (only one disk)
			array('V', 0x0000),             // number of disk with central dir
			array('V', $num_low),           // number of entries in the cdr for this disk (low)
			array('V', $num_high),          // number of entries in the cdr for this disk (high)
			array('V', $num_low),           // number of entries in the cdr (low)
			array('V', $num_high),          // number of entries in the cdr (high)
			array('V', $cdr_len_low),       // cdr size (low)
			array('V', $cdr_len_high),      // cdr size (high)
			array('V', $cdr_ofs_low),       // cdr ofs (low)
			array('V', $cdr_ofs_high),      // cdr ofs (high)
		);

		$ret = $this->pack_fields($fields);
		$this->send($ret);
	}

	/**
	 * Add location record for ZIP64 central directory
	 *
	 * @access private
	 */
	private function add_cdr_eof_locator_zip64()
	{
		list($cdr_ofs_low, $cdr_ofs_high) = $this->int64_split($this->cdr_len + $this->cdr_ofs);

		$fields = array(                    // (from V,F of APPNOTE.TXT)
			array('V', 0x07064b50),         // zip64 end of central dir locator signature
			array('V', 0),                  // this disk number
			array('V', $cdr_ofs_low),       // cdr ofs (low)
			array('V', $cdr_ofs_high),      // cdr ofs (high)
			array('V', 1),                  // total number of disks
		);

		$ret = $this->pack_fields($fields);
		$this->send($ret);
	}

	/**
	 * Send CDR EOF (Central Directory Record End-of-File) record. Most values
	 * point to the corresponding values in the ZIP64 CDR. The optional comment
	 * still goes in this CDR however.
	 *
	 * @param array $opt options array that may contain a comment
	 * @access private
	 */
	private function add_cdr_eof($opt = null)
	{
		// grab comment (if specified)
		$comment = '';
		if ($opt && isset($opt['comment']))
		{
			$comment = $opt['comment'];
		}

		$fields = array(                    // (from V,F of APPNOTE.TXT)
			array('V', 0x06054b50),         // end of central file header signature
			array('v', 0xFFFF),             // this disk number (0xFFFF to look in zip64 cdr)
			array('v', 0xFFFF),             // number of disk with cdr (0xFFFF to look in zip64 cdr)
			array('v', 0xFFFF),             // number of entries in the cdr on this disk (0xFFFF to look in zip64 cdr))
			array('v', 0xFFFF),             // number of entries in the cdr (0xFFFF to look in zip64 cdr)
			array('V', 0xFFFFFFFF),         // cdr size (0xFFFFFFFF to look in zip64 cdr)
			array('V', 0xFFFFFFFF),         // cdr offset (0xFFFFFFFF to look in zip64 cdr)
			array('v', strlen($comment)),   // zip file comment length
		);

		$ret = $this->pack_fields($fields) . $comment;
		$this->send($ret);
	}

	/**
	 * Add CDR (Central Directory Record) footer.
	 *
     * @param array $opt options array that may contain a comment
	 * @access private
	 */
	private function add_cdr($opt = null)
	{
		foreach ($this->files as $file)
		{
			$this->add_cdr_file($file);
		}

		$this->add_cdr_eof_zip64();
		$this->add_cdr_eof_locator_zip64();

		$this->add_cdr_eof($opt);
	}

	/**
	 * Clear all internal variables.
	 *
	 * Note: that the stream object is not usable after this.
	 *
	 * @access private
	 */
	private function clear()
	{
		$this->files = array();
		$this->cdr_ofs = 0;
		$this->cdr_len = 0;
		$this->opt = array();
	}
}
