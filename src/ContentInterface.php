<?php
namespace Genkgo\ArchiveStream;

interface ContentInterface {

    /**
     * @return string
     */
    public function getName();

    /**
     * @return resource
     */
    public function getData();

}