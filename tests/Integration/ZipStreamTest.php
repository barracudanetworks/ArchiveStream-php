<?php
namespace Genkgo\ArchiveStream\Integration;

use Genkgo\ArchiveStream\AbstractTestCase;
use Genkgo\ArchiveStream\Archive;
use Genkgo\ArchiveStream\StringContent;
use Genkgo\ArchiveStream\ZipStream;

final class ZipStreamTest extends AbstractTestCase {

    public function testCreateFileZip () {
        $archive = new Archive();
        $archive->addContent(new StringContent('test.txt', 'content'));

        $filename = tempnam(sys_get_temp_dir(), 'zip');
        $fileStream = fopen($filename, 'r+');

        $zipStream = new ZipStream($archive);
        $generator = $zipStream->read(1048576);
        foreach ($generator as $stream) {
            while ($stream->eof() === false) {
                $data = $stream->fread(9999);
                fwrite($fileStream, $data);
            }
        }
        fclose($fileStream);

        $zip = new \ZipArchive();
        $result = $zip->open($filename);

        $this->assertTrue($result);
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals('test.txt', $zip->getNameIndex(0));
        $this->assertEquals('content', $zip->getFromIndex(0));
    }

    public function testCreateEmptyDirectory () {
        $archive = new Archive();
        $archive->addDirectory('empty');

        $filename = tempnam(sys_get_temp_dir(), 'zip');
        $fileStream = fopen($filename, 'r+');

        $zipStream = new ZipStream($archive);
        $generator = $zipStream->read(1048576);
        foreach ($generator as $stream) {
            while ($stream->eof() === false) {
                $data = $stream->fread(9999);
                fwrite($fileStream, $data);
            }
        }
        fclose($fileStream);

        $zip = new \ZipArchive();
        $result = $zip->open($filename);

        $this->assertTrue($result);
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals('empty/', $zip->getNameIndex(0));
    }

    public function testCreateFileEmptyDirectory () {
        $archive = new Archive();
        $archive->addDirectory('directory');
        $archive->addContent(new StringContent('other/file.txt', 'data'));

        $filename = tempnam(sys_get_temp_dir(), 'zip');
        $fileStream = fopen($filename, 'r+');

        $zipStream = new ZipStream($archive);
        $generator = $zipStream->read(1048576);
        foreach ($generator as $stream) {
            while ($stream->eof() === false) {
                $data = $stream->fread(9999);
                fwrite($fileStream, $data);
            }
        }
        fclose($fileStream);

        $zip = new \ZipArchive();
        $result = $zip->open($filename);

        $this->assertTrue($result);
        $this->assertEquals(2, $zip->numFiles);
        $this->assertEquals('other/file.txt', $zip->getNameIndex(0));
        $this->assertEquals('directory/', $zip->getNameIndex(1));
        $this->assertEquals('data', $zip->getFromIndex(0));
    }
}