<?php
namespace Genkgo\ArchiveStream;

use Genkgo\ArchiveStream\Util\PackHelper;

/**
 * Class ZipStream
 * @package Genkgo\ArchiveStream
 */
final class ZipStream implements ArchiveStream {

    /**
     * Version zip was created by / must be opened by (4.5 for Zip64 support).
     */
    const VERSION = 45;

    /**
     * @var Archive
     */
    private $archive;
    /**
     * @var int|null
     */
    private $len = null;
    /**
     * @var resource
     */
    private $hash_ctx;
    /**
     * @var
     */
    private $zlen = null;
    /**
     * @var
     */
    private $current_file_stream;
    /**
     * @var
     */
    private $files = [];
    /**
     * @var
     */
    private $cdr_ofs = 0;
    /**
     * @var int
     */
    private $cdr_len;

    /**
     * @param Archive $archive
     */
    public function __construct(Archive $archive)
    {
        $this->archive = $archive;
    }

    /**
     * @param $blockSize
     * @return \Generator|\SplTempFileObject[]
     */
    public function read($blockSize) {
        foreach ($this->archive->getContents() as $content) {
            yield $this->initializeResourceStream($content->getName());

            $resource = $content->getData();
            while ($data = fread($resource, $blockSize))
            {
                yield $this->streamResourceData($data);
            }

            // close input file
            fclose($resource);

            yield $this->completeResourceStream();
        }

        foreach ($this->archive->getDirectories() as $directory) {
            // calculate header attributes
            $method = 0x08;

            if (substr($directory, -1) != '/')
            {
                $directory = $directory . '/';
            }

            // send header
            yield $this->initializeResourceStream($directory, $method);

            // complete the file stream
            yield $this->completeResourceStream();
        }

        foreach ($this->files as $file)
        {
            yield $this->addCdrFile($file);
        }

        yield $this->addCdrEofZip64();
        yield $this->addCdrEofLocatorZip64();
        yield $this->addCdrEof();

        $this->clear();
    }

    /**
     * Initialize a file stream
     *
     * @param string $name File path or just name.
     * @param int    $method Method of compression to use (defaults to store).
     * @return string
     */
    private function initializeResourceStream($name, $method = 0x00)
    {
        $algorithm = 'crc32b';

        // calculate header attributes
        $this->len = gmp_init(0);
        $this->zlen = gmp_init(0);
        $this->hash_ctx = hash_init($algorithm);

        // Send file header
        return $this->sendFileHeader($name, $method);
    }

    /**
     * Add initial headers for file stream
     *
     * @param string $name File path or just name.
     * @param int    $method Method of compression to use.
     * @return string
     */
    private function sendFileHeader($name,  $method)
    {
        // strip leading slashes from file name
        // (fixes bug in windows archive viewer)
        $name = preg_replace('/^\\/+/', '', $name);
        $extra = pack('vVVVV', 1, 0, 0, 0, 0);

        // create dos timestamp
        $dts = PackHelper::dostime();

        // Sets bit 3, which means CRC-32, uncompressed and compresed length
        // are put in the data descriptor following the data. This gives us time
        // to figure out the correct sizes, etc.
        $genb = 0x08;

        if (mb_check_encoding($name, "UTF-8") && !mb_check_encoding($name, "ASCII"))
        {
            // Sets Bit 11: Language encoding flag (EFS).  If this bit is set,
            // the filename and comment fields for this file
            // MUST be encoded using UTF-8. (see APPENDIX D)
            $genb |= 0x0800;
        }

        // build file header
        $fields = [                // (from V.A of APPNOTE.TXT)
            ['V', 0x04034b50],     // local file header signature
            ['v', self::VERSION],  // version needed to extract
            ['v', $genb],          // general purpose bit flag
            ['v', $method],          // compresion method (deflate or store)
            ['V', $dts],           // dos timestamp
            ['V', 0x00],           // crc32 of data (0x00 because bit 3 set in $genb)
            ['V', 0xFFFFFFFF],     // compressed data length
            ['V', 0xFFFFFFFF],     // uncompressed data length
            ['v', strlen($name)],  // filename length
            ['v', strlen($extra)], // extra data len
        ];

        // pack fields and calculate "total" length
        $ret = PackHelper::packFields($fields);

        // Keep track of data for central directory record
        $this->current_file_stream = [
            $name,
            $method,
            // 2-4 will be filled in by complete_file_stream()
            5 => (strlen($ret) + strlen($name) + strlen($extra)),
            6 => $genb,
        ];

        $stream = new \SplTempFileObject();
        $stream->fwrite($ret . $name . $extra);
        $stream->rewind();
        return $stream;
    }

    /**
     * @param $data
     * @return \SplTempFileObject
     */
    private function streamResourceData($data)
    {
        $this->len = gmp_add(gmp_init(strlen($data)), $this->len);
        hash_update($this->hash_ctx, $data);

        $this->zlen = gmp_add(gmp_init(strlen($data)), $this->zlen);

        $stream = new \SplTempFileObject();
        $stream->fwrite($data);
        $stream->rewind();

        return $stream;
    }

    /**
     * @return \SplTempFileObject
     */
    private function completeResourceStream()
    {
        $crc = hexdec(hash_final($this->hash_ctx));

        // convert the 64 bit ints to 2 32bit ints
        list($zlen_low, $zlen_high) = PackHelper::int64Split($this->zlen);
        list($len_low, $len_high) = PackHelper::int64Split($this->len);

        // build data descriptor
        $fields = [                // (from V.A of APPNOTE.TXT)
            ['V', 0x08074b50],     // data descriptor
            ['V', $crc],           // crc32 of data
            ['V', $zlen_low],      // compressed data length (low)
            ['V', $zlen_high],     // compressed data length (high)
            ['V', $len_low],       // uncompressed data length (low)
            ['V', $len_high],      // uncompressed data length (high)
        ];

        // pack fields and calculate "total" length
        $data = PackHelper::packFields($fields);

        // Update cdr for file record
        $this->current_file_stream[2] = $crc;
        $this->current_file_stream[3] = gmp_strval($this->zlen);
        $this->current_file_stream[4] = gmp_strval($this->len);
        $this->current_file_stream[5] += gmp_strval(gmp_add(gmp_init(strlen($data)), $this->zlen));
        ksort($this->current_file_stream);

        // Add to cdr and increment offset - can't call directly because we pass an array of params
        $this->addToCdr(...$this->current_file_stream);

        $stream = new \SplTempFileObject();
        $stream->fwrite($data);
        $stream->rewind();
        return $stream;
    }

    /**
     * Save file attributes for trailing CDR record.
     *
     * @param string $name    Path / name of the file.
     * @param int    $method    Method of compression to use.
     * @param string $crc     Computed checksum of the file.
     * @param int    $zlen    Compressed size.
     * @param int    $len     Uncompressed size.
     * @param int    $rec_len Size of the record.
     * @param int    $genb    General purpose bit flag.
     * @return void
     */
    private function addToCdr($name, $method, $crc, $zlen, $len, $rec_len, $genb = 0)
    {
        $this->files[] = [$name, $method, $crc, $zlen, $len, $this->cdr_ofs, $genb];
        $this->cdr_ofs += $rec_len;
    }

    /**
     * Send CDR record for specified file (Zip64 format).
     *
     * @see add_to_cdr() for options to pass in $args.
     * @param array $args Array of argumentss.
     * @return \SplTempFileObject
     */
    private function addCdrFile(array $args)
    {
        list($name, $meth, $crc, $zlen, $len, $ofs, $genb) = $args;

        // convert the 64 bit ints to 2 32bit ints
        list($zlen_low, $zlen_high) = PackHelper::int64Split($zlen);
        list($len_low, $len_high)   = PackHelper::int64Split($len);
        list($ofs_low, $ofs_high)   = PackHelper::int64Split($ofs);

        // ZIP64, necessary for files over 4GB (incl. entire archive size)
        $extra_zip64 = '';
        $extra_zip64 .= pack('VV', $len_low, $len_high);
        $extra_zip64 .= pack('VV', $zlen_low, $zlen_high);
        $extra_zip64 .= pack('VV', $ofs_low, $ofs_high);

        $extra = pack('vv', 1, strlen($extra_zip64)) . $extra_zip64;

        // get attributes
        $comment = '';

        // get dos timestamp
        $dts = PackHelper::dostime();

        $fields = [                      // (from V,F of APPNOTE.TXT)
            ['V', 0x02014b50],           // central file header signature
            ['v', self::VERSION],        // version made by
            ['v', self::VERSION],        // version needed to extract
            ['v', $genb],                // general purpose bit flag
            ['v', $meth],                // compresion method (deflate or store)
            ['V', $dts],                 // dos timestamp
            ['V', $crc],                 // crc32 of data
            ['V', 0xFFFFFFFF],           // compressed data length (zip64 - look in extra)
            ['V', 0xFFFFFFFF],           // uncompressed data length (zip64 - look in extra)
            ['v', strlen($name)],        // filename length
            ['v', strlen($extra)],       // extra data len
            ['v', strlen($comment)],     // file comment length
            ['v', 0],                    // disk number start
            ['v', 0],                    // internal file attributes
            ['V', 32],                   // external file attributes
            ['V', 0xFFFFFFFF],           // relative offset of local header (zip64 - look in extra)
        ];

        // pack fields, then append name and comment
        $ret = PackHelper::packFields($fields) . $name . $extra . $comment;

        $this->cdr_len += strlen($ret);

        $stream = new \SplTempFileObject();
        $stream->fwrite($ret);
        $stream->rewind();
        return $stream;
    }

    /**
     * Adds Zip64 end of central directory record.
     *
     * @return \SplTempFileObject
     */
    private function addCdrEofZip64()
    {
        $num = count($this->files);

        list($num_low, $num_high) = PackHelper::int64Split($num);
        list($cdr_len_low, $cdr_len_high) = PackHelper::int64Split($this->cdr_len);
        list($cdr_ofs_low, $cdr_ofs_high) = PackHelper::int64Split($this->cdr_ofs);

        $fields = [                    // (from V,F of APPNOTE.TXT)
            ['V', 0x06064b50],         // zip64 end of central directory signature
            ['V', 44],                 // size of zip64 end of central directory record (low) minus 12 bytes
            ['V', 0],                  // size of zip64 end of central directory record (high)
            ['v', self::VERSION],      // version made by
            ['v', self::VERSION],      // version needed to extract
            ['V', 0x0000],             // this disk number (only one disk)
            ['V', 0x0000],             // number of disk with central dir
            ['V', $num_low],           // number of entries in the cdr for this disk (low)
            ['V', $num_high],          // number of entries in the cdr for this disk (high)
            ['V', $num_low],           // number of entries in the cdr (low)
            ['V', $num_high],          // number of entries in the cdr (high)
            ['V', $cdr_len_low],       // cdr size (low)
            ['V', $cdr_len_high],      // cdr size (high)
            ['V', $cdr_ofs_low],       // cdr ofs (low)
            ['V', $cdr_ofs_high],      // cdr ofs (high)
        ];

        $ret = PackHelper::packFields($fields);
        $stream = new \SplTempFileObject();
        $stream->fwrite($ret);
        $stream->rewind();
        return $stream;
    }

    /**
     * Add location record for ZIP64 central directory
     *
     * @return \SplTempFileObject
     */
    private function addCdrEofLocatorZip64()
    {
        list($cdr_ofs_low, $cdr_ofs_high) = PackHelper::int64Split($this->cdr_len + $this->cdr_ofs);

        $fields = [                    // (from V,F of APPNOTE.TXT)
            ['V', 0x07064b50],         // zip64 end of central dir locator signature
            ['V', 0],                  // this disk number
            ['V', $cdr_ofs_low],       // cdr ofs (low)
            ['V', $cdr_ofs_high],      // cdr ofs (high)
            ['V', 1],                  // total number of disks
        ];

        $ret = PackHelper::packFields($fields);
        $stream = new \SplTempFileObject();
        $stream->fwrite($ret);
        $stream->rewind();
        return $stream;
    }

    /**
     * Send CDR EOF (Central Directory Record End-of-File) record. Most values
     * point to the corresponding values in the ZIP64 CDR. The optional comment
     * still goes in this CDR however.
     *
     * @return \SplTempFileObject
     */
    private function addCdrEof()
    {
        // grab comment (if specified)
        $comment = '';

        $fields = [                    // (from V,F of APPNOTE.TXT)
            ['V', 0x06054b50],         // end of central file header signature
            ['v', 0xFFFF],             // this disk number (0xFFFF to look in zip64 cdr)
            ['v', 0xFFFF],             // number of disk with cdr (0xFFFF to look in zip64 cdr)
            ['v', 0xFFFF],             // number of entries in the cdr on this disk (0xFFFF to look in zip64 cdr))
            ['v', 0xFFFF],             // number of entries in the cdr (0xFFFF to look in zip64 cdr)
            ['V', 0xFFFFFFFF],         // cdr size (0xFFFFFFFF to look in zip64 cdr)
            ['V', 0xFFFFFFFF],         // cdr offset (0xFFFFFFFF to look in zip64 cdr)
            ['v', strlen($comment)],   // zip file comment length
        ];

        $ret = PackHelper::packFields($fields) . $comment;
        $stream = new \SplTempFileObject();
        $stream->fwrite($ret);
        $stream->rewind();
        return $stream;
    }

    /**
     * @return void
     */
    private function clear()
    {
        $this->files = array();
        $this->cdr_ofs = 0;
        $this->cdr_len = 0;
    }
}