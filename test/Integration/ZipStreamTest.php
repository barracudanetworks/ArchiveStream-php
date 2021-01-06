<?php

declare(strict_types=1);

namespace Genkgo\TestArchiveStream\Integration;

use Genkgo\ArchiveStream\Archive;
use Genkgo\ArchiveStream\EmptyDirectory;
use Genkgo\ArchiveStream\StringContent;
use Genkgo\ArchiveStream\ZipReader;
use Genkgo\TestArchiveStream\AbstractTestCase;

final class ZipStreamTest extends AbstractTestCase
{
    public function testCreateFileZip()
    {
        $archive = (new Archive())->withContent(new StringContent('test.txt', 'content'));

        $filename = \tempnam(\sys_get_temp_dir(), 'zip');
        $fileStream = \fopen($filename, 'r+');

        $zipStream = new ZipReader($archive);
        $generator = $zipStream->read(1048576);
        foreach ($generator as $stream) {
            while ($stream->eof() === false) {
                $data = $stream->fread(9999);
                \fwrite($fileStream, $data);
            }
        }
        \fclose($fileStream);

        $zip = new \ZipArchive();
        $result = $zip->open($filename);

        $this->assertTrue($result);
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals('test.txt', $zip->getNameIndex(0));
        $this->assertEquals('content', $zip->getFromIndex(0));
    }

    public function testCreateEmptyDirectory()
    {
        $archive = (new Archive())->withContent(new EmptyDirectory('empty'));

        $filename = \tempnam(\sys_get_temp_dir(), 'zip');
        $fileStream = \fopen($filename, 'r+');

        $zipStream = new ZipReader($archive);
        $generator = $zipStream->read(1048576);
        foreach ($generator as $stream) {
            while ($stream->eof() === false) {
                $data = $stream->fread(9999);
                \fwrite($fileStream, $data);
            }
        }
        \fclose($fileStream);

        $zip = new \ZipArchive();
        $result = $zip->open($filename);

        $this->assertTrue($result);
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals('empty/', $zip->getNameIndex(0));
    }

    public function testCreateFileEmptyDirectory()
    {
        $archive = (new Archive())
            ->withContent(new EmptyDirectory('directory'))
            ->withContent(new StringContent('other/file.txt', 'data'));

        $filename = \tempnam(\sys_get_temp_dir(), 'zip');
        $fileStream = \fopen($filename, 'r+');

        $zipStream = new ZipReader($archive);
        $generator = $zipStream->read(1048576);
        foreach ($generator as $stream) {
            while ($stream->eof() === false) {
                $data = $stream->fread(9999);
                \fwrite($fileStream, $data);
            }
        }
        \fclose($fileStream);

        $zip = new \ZipArchive();
        $result = $zip->open($filename);

        $this->assertTrue($result);
        $this->assertEquals(2, $zip->numFiles);
        $this->assertEquals('directory/', $zip->getNameIndex(0));
        $this->assertEquals('other/file.txt', $zip->getNameIndex(1));
        $this->assertEquals('data', $zip->getFromIndex(1));
    }
}
