<?php

require_once(__DIR__ . '/stream.php');

class ArchiveStream_Tar extends ArchiveStream
{
	const REGTYPE = 0;
	const DIRTYPE = 5;
	const XHDTYPE = 'x';

	// initialize the options array
	public $opt = array();

	/**
	 * Create a new ArchiveStream_Tar object.
	 *
	 * @see ArchiveStream for documentation
	 * @access public
	 */
	public function __construct()
	{
		call_user_func_array(array('parent', '__construct'), func_get_args());
		$this->opt['content_type'] = 'application/x-tar';
	}

	/**
	 * Explicitly adds a directory to the tar (necessary for empty directories)
	 *
	 * @param  string $name Name (path) of the directory
	 * @param  array  $opt  Additional options to set ("type" will be overrided)
	 * @return void
	 */
	function add_directory($name, $opt = array())
	{
		// calculate header attributes
		$this->meth_str = 'deflate';
		$meth = 0x08;

		$opt['type'] = self::DIRTYPE;

		// send header
		$this->init_file_stream_transfer($name, $size = 0, $opt, $meth);

		// complete the file stream
		$this->complete_file_stream();
	}

	/**
	 * Initialize a file stream
	 *
	 * @param string $name file path or just name
	 * @param int $size size in bytes of the file
	 * @param array $opt array containing time / type (optional)
	 * @param int $meth method of compression to use (not used in this class)
	 * @access public
	 */
	public function init_file_stream_transfer($name, $size, $opt = array(), $meth = null)
	{
		// try to detect the type if not provided
		$type = self::REGTYPE;
		if (isset($opt['type']))
		{
			$type = $opt['type'];
		}
		elseif (substr($name, -1) == '/')
		{
			$type = self::DIRTYPE;
		}

		$dirname = dirname($name);
		$name = basename($name);

		// Remove '.' from the current directory
		$dirname = ($dirname == '.') ? '' : $dirname;

		// if we're using a container directory, prepend it to the filename
		if ($this->use_container_dir)
		{
			// the container directory will end with a '/' so ensure the lower level directory name doesn't start with one
			$dirname = $this->container_dir_name . preg_replace('/^\/+/', '', $dirname);
		}

		// Remove trailing slash from directory name, because tar implies it.
		if (substr($dirname, -1) == '/')
		{
			$dirname = substr($dirname, 0, -1);
		}

		// handle long file names via PAX
		if (strlen($name) > 99 || strlen($dirname) > 154)
		{
			$pax = $this->__pax_generate(array(
				'path' => $dirname . '/' . $name
			));

			$this->init_file_stream_transfer('', strlen($pax), array(
				'type' => self::XHDTYPE
			));

			$this->stream_file_part($pax, $single_part = true);
			$this->complete_file_stream();
		}

		// stash the file size for later use
		$this->file_size = $size;

		// process optional arguments
		$time = isset($opt['time']) ? $opt['time'] : time();

		// build data descriptor
		$fields = array(
			array('a100', substr($name, 0, 100)),
			array('a8',   str_pad('777', 7,  '0', STR_PAD_LEFT)),
			array('a8',   decoct(str_pad('0',     7,  '0', STR_PAD_LEFT))),
			array('a8',   decoct(str_pad('0',     7,  '0', STR_PAD_LEFT))),
			array('a12',  decoct(str_pad($size,   11, '0', STR_PAD_LEFT))),
			array('a12',  decoct(str_pad($time,   11, '0', STR_PAD_LEFT))),
			array('a8',   ''),
			array('a1',   $type),
			array('a100', ''),
			array('a6',   'ustar'),
			array('a2',   '00'),
			array('a32',  ''),
			array('a32',  ''),
			array('a8',   ''),
			array('a8',   ''),
			array('a155', substr($dirname, 0, 155)),
			array('a12',  ''),
		);

		// pack fields and calculate "total" length
		$header = $this->pack_fields($fields);

		// Compute header checksum
		$checksum = str_pad(decoct($this->__computeUnsignedChecksum($header)),6,"0",STR_PAD_LEFT);
		for($i=0; $i<6; $i++)
		{
			$header[(148 + $i)] = substr($checksum,$i,1);
		}
		$header[154] = chr(0);
		$header[155] = chr(32);

		// print header
		$this->send($header);
	}

	/**
	 * Stream the next part of the current file stream.
	 *
	 * @param $data raw data to send
	 * @param bool $single_part used to determin if we can compress (not used in this class)
	 * @access public
	 */
	function stream_file_part( $data, $single_part = false )
	{
		// send data
		$this->send($data);

		// flush the data to the output
		flush();
	}

	/**
	 * Complete the current file stream
	 *
	 * @access private
	 */
	public function complete_file_stream()
	{
		// ensure we pad the last block so that it is 512 bytes
		if (($mod = ($this->file_size % 512)) > 0)
			$this->send( pack('a' . (512 - $mod) , '') );

		// flush the data to the output
		flush();
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

		// tar requires the end of the file have two 512 byte null blocks
		$this->send( pack('a1024', '') );

		// flush the data to the output
		flush();
	}

	/*******************
	 * PRIVATE METHODS *
	 *******************/

	/**
	 * Generate unsigned checksum of header
	 *
	 * @param string $header
	 * @return string unsigned checksum
	 * @access private
	 */
	private function __computeUnsignedChecksum($header)
	{
		$unsigned_checksum = 0;
		for($i=0; $i<512; $i++)
			$unsigned_checksum += ord($header[$i]);
		for($i=0; $i<8; $i++)
			$unsigned_checksum -= ord($header[148 + $i]);
		$unsigned_checksum += ord(" ") * 8;

		return $unsigned_checksum;
	}

	/**
	 * Generate a PAX string
	 *
	 * @param array $fields key value mapping
	 * @return string PAX formated string
	 * @link http://www.freebsd.org/cgi/man.cgi?query=tar&sektion=5&manpath=FreeBSD+8-current tar / PAX spec
	 * @access private
	 */
	private function __pax_generate($fields)
	{
		$lines = '';
		foreach ($fields as $name => $value)
		{
			// build the line and the size
			$line = ' ' . $name . '=' . $value . "\n";
			$size = strlen(strlen($line)) + strlen($line);

			// add the line
			$lines .= $size . $line;
		}

		return $lines;
	}
}
