<?php

require_once(__DIR__ . '/stream.php');

class ArchiveStream_Zip extends ArchiveStream
{
	// initialize the options array
	var $opt = array(),
		$files = array(),
		$cdr_ofs = 0,
		$ofs = 0;


	// track the need to use zip64
	private $zip64 = false;

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
	function init_file_stream_transfer( $name, $size, $opt = array(), $meth = 0x00 )
	{
		$algo = 'crc32b';

		// calculate header attributes
		$this->len = gmp_init(0);
		$this->zlen = gmp_init(0);
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
	function stream_file_part( $data, $single_part = false )
	{
		$this->len = gmp_add( gmp_init(strlen($data)), $this->len);
		hash_update($this->hash_ctx, $data);

		if (  $single_part === true && isset($this->meth_str) && $this->meth_str == 'deflate' )
			$data = gzdeflate($data);

		$this->zlen = gmp_add( gmp_init(strlen($data)), $this->zlen);

		// send data
		$this->send($data);
		flush();
	}

	/**
	 * Complete the current file stream
	 *
	 * @access private
	 */
 	function complete_file_stream()
	{
		$crc = hexdec(hash_final($this->hash_ctx));

		// build data descriptor
		$fields = array(                // (from V.A of APPNOTE.TXT)
			array('V', 0x08074b50),     // data descriptor
			array('V', $crc),           // crc32 of data
		);

		if ($this->current_file_stream[8] === true)
		{
			// flip the global and file zip64 flags
			$this->zip64 = true;
			$this->current_file_stream[8] = true;

			// convert the 64 bit ints to 2 32bit ints
			list($zlen_low, $zlen_high) = $this->int64_split($this->zlen);
			list($len_low, $len_high) = $this->int64_split($this->len);

			$fields_len = array(
				array('V', $zlen_low),       // compressed data length (low)
				array('V', $zlen_high),      // compressed data length (high)
				array('V', $len_low),       // uncompressed data length (low)
				array('V', $len_high),      // uncompressed data length (high)
			);
		}
		else
		{
			$fields_len = array(
				array('V', gmp_strval($this->zlen)),  // compressed data length
				array('V', gmp_strval($this->len)),  // uncompressed data length
			);
		}

		// pack fields and calculate "total" length
		$ret = $this->pack_fields($fields) . $this->pack_fields($fields_len);

		// print header and filename
		$this->send($ret);

		// Update cdr for file record
		$this->current_file_stream[3] = $crc;
		$this->current_file_stream[4] = gmp_strval($this->zlen);
		$this->current_file_stream[5] = gmp_strval($this->len);
		$this->current_file_stream[6] += gmp_strval( gmp_add( gmp_init(strlen($ret)), $this->zlen ) );
		ksort($this->current_file_stream);

		// Add to cdr and increment offset
		call_user_func_array(array($this, 'add_to_cdr'), $this->current_file_stream);
	}

	/**
	 * Finish an archive
	 *
	 * @access public
	 */
	function finish()
	{
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
	protected function add_stream_file_header( $name, $size, $opt, $meth )
	{
		// strip leading slashes from file name
		// (fixes bug in windows archive viewer)
		$name = preg_replace('/^\\/+/', '', $name);

		// ZIP64
		if ($this->is64bit(gmp_init($size)) === true)
		{
			$this->zip64 = true;
			$zip64 = true;
			$extra = pack('vVVVV', 1, 0, 0, 0, 0);
			$version = 45;
		}
		else
		{
			$zip64 = false;
			$extra = '';
			$version = 20;
		}

		// calculate name length
		$nlen = strlen($name);

		// create dos timestamp
		$opt['time'] = isset($opt['time']) ? $opt['time'] : time();
		$dts = $this->dostime($opt['time']);
		$genb = 0x0808;

		// build file header
		$fields = array(                // (from V.A of APPNOTE.TXT)
			array('V', 0x04034b50),     // local file header signature
			array('v', $version),       // version needed to extract
			array('v', $genb),          // general purpose bit flag
			array('v', $meth),          // compresion method (deflate or store)
			array('V', $dts),           // dos timestamp
			array('V', 0x00),           // crc32 of data
			array('V', 0xFFFFFFFF),     // compressed data length
			array('V', 0xFFFFFFFF),     // uncompressed data length
			array('v', $nlen),          // filename length
			array('v', strlen($extra)), // extra data len
		);

		// pack fields and calculate "total" length
		$ret = $this->pack_fields($fields);

		// print header and filename
		$this->send($ret . $name . $extra);

		// Keep track of data for central directory record
		$this->current_file_stream = array($name, $opt, $meth, 6 => (strlen($ret) + $nlen + strlen($extra)), 7 => $genb, 8 => $zip64);
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
	 * @param bool $zip64 true for 64bit
	 * @access private
	 */
	private function add_to_cdr( $name, $opt, $meth, $crc, $zlen, $len, $rec_len, $genb = 0, $zip64 = false )
	{
		$this->files[] = array($name, $opt, $meth, $crc, $zlen, $len, $this->ofs, $genb, $zip64);
		$this->ofs += $rec_len;
	}

	/**
	 * Send CDR record for specified file.
	 *
	 * @param array $args array of args
	 * @see add_to_cdr() for details of the args
	 * @access private
	 */
	private function add_cdr_file( $args )
	{
		list ($name, $opt, $meth, $crc, $zlen, $len, $ofs, $genb, $zip64) = $args;

		if ($zip64 === true)
		{
			// bump version for zip64 support
			$version = 45;

			// convert the 64 bit ints to 2 32bit ints
			list($zlen_low, $zlen_high) = $this->int64_split($zlen);
			list($len_low, $len_high)   = $this->int64_split($len);
			list($ofs_low, $ofs_high)   = $this->int64_split($ofs);

			// ZIP64
			$extra_zip64 = '';
			$extra_zip64 .= pack('VV', $len_low, $len_high);
			$extra_zip64 .= pack('VV', $zlen_low, $zlen_high);
			$extra_zip64 .= pack('VV', $ofs_low, $ofs_high);

			$extra = pack('vv', 1, strlen($extra_zip64)) . $extra_zip64;
			$zlen = $len = 0xFFFFFFFF;
			$ofs = 0xFFFFFFFF;
		}
		else
		{
			$version = 20;
			$extra = '';
		}

		// get attributes
		$comment = isset($opt['comment']) ? $opt['comment'] : '';

		// get dos timestamp
		$dts = $this->dostime($opt['time']);

		$fields = array(                      // (from V,F of APPNOTE.TXT)
			array('V', 0x02014b50),           // central file header signature
			array('v', $version),             // version made by
			array('v', $version),             // version needed to extract
			array('v', $genb),                // general purpose bit flag
			array('v', $meth),                // compresion method (deflate or store)
			array('V', $dts),                 // dos timestamp
			array('V', $crc),                 // crc32 of data
			array('V', $zlen),                // compressed data length
			array('V', $len),                 // uncompressed data length
			array('v', strlen($name)),        // filename length
			array('v', strlen($extra)),       // extra data len
			array('v', strlen($comment)),     // file comment length
			array('v', 0),                    // disk number start
			array('v', 0),                    // internal file attributes
			array('V', 32),                   // external file attributes
			array('V', $ofs),                 // relative offset of local header
		);

		// pack fields, then append name and comment
		$ret = $this->pack_fields($fields) . $name . $extra . $comment;

		$this->send($ret);

		// increment cdr offset
		$this->cdr_ofs += strlen($ret);
	}

	/**
	 * Send CDR EOF (Central Directory Record End-of-File) record.
	 *
	 * @param array $opt options array that may contain a comment
	 * @access private
	 */
	private function add_cdr_eof( $opt = null )
	{
		$num = count($this->files);
		$cdr_len = $this->cdr_ofs;
		$cdr_ofs = $this->ofs;

		if ($this->zip64 === true)
		{
			if ($num > 0xFFFF) $num = 0xFFFF;
			if ($cdr_len > 0xFFFFFFFF) $cdr_len = 0xFFFFFFFF;
			$cdr_ofs = 0xFFFFFFFF;
		}

		// grab comment (if specified)
		$comment = '';
		if ($opt && isset($opt['comment']))
			$comment = $opt['comment'];

		$fields = array(                    // (from V,F of APPNOTE.TXT)
			array('V', 0x06054b50),         // end of central file header signature
			array('v', 0x00),               // this disk number
			array('v', 0x00),               // number of disk with cdr
			array('v', $num),               // number of entries in the cdr on this disk
			array('v', $num),               // number of entries in the cdr
			array('V', $cdr_len),           // cdr size
			array('V', $cdr_ofs),           // cdr ofs
			array('v', strlen($comment)),   // zip file comment length
		);

		$ret = $this->pack_fields($fields) . $comment;
		$this->send($ret);
	}

	/**
	 * Add central directory for ZIP64
	 *
	 * @param int $cdr_start the offset where the cdr starts
	 * @access private
	 */
	private function add_cdr_zip64($cdr_start)
	{
		$num = count($this->files);
		$cdr_len = $this->cdr_ofs;

		list($num_low, $num_high) = $this->int64_split($num);
		list($cdr_len_low, $cdr_len_high) = $this->int64_split($cdr_len);
		list($cdr_ofs_low, $cdr_ofs_high) = $this->int64_split($cdr_start);

		$fields = array(                    // (from V,F of APPNOTE.TXT)
			array('V', 0x06064b50),         // zip64 end of central file header signature
			array('V', 44),                 // size of zip64 end of central directory record (low)
			array('V', 0),                  // size of zip64 end of central directory record (high)
			array('v', 45),                 // version made by
			array('v', 45),                 // version needed to extract
			array('V', 0x0000),             // this disk number
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
	private function add_cdr_eof_zip64()
	{
		$cdr_len = $this->cdr_ofs;
		$cdr_ofs = $this->ofs;

		list($cdr_ofs_low, $cdr_ofs_high) = $this->int64_split($cdr_len + $cdr_ofs);

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
	 * Add CDR (Central Directory Record) footer.
	 *
     * @param array $opt options array that may contain a comment
	 * @access private
	 */
	private function add_cdr( $opt = null )
	{
		$cdr_start = $this->ofs;

		foreach ($this->files as $file)
			$this->add_cdr_file($file);

		if ($this->zip64 || sizeof($this->files) > 65535)
		{
			$this->add_cdr_zip64($cdr_start);
			$this->add_cdr_eof_zip64();
		}

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
		$this->ofs = 0;
		$this->cdr_ofs = 0;
		$this->opt = array();
	}
}
