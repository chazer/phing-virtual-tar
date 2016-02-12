<?php
/**
 * ReadOnlyFileContent.php
 *
 * @author: chazer
 * @created: 12.02.16 15:07
 */

namespace PhingVirtualTar;

use org\bovigo\vfs\content\SeekableFileContent;

class ReadOnlyFileContent extends SeekableFileContent
{
    private $file;

    private $handle;

    /**
     * @param string $file file path
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    protected function open()
    {
        if (!is_resource($this->handle)) {
            $this->handle = fopen($this->file, 'rb');
        }
    }

    protected function close()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function eof()
    {
        if ($eof = parent::eof()) {
            $this->close();
        }
        return $eof;
    }

    /**
     * returns size of content
     *
     * @return  int
     */
    public function size()
    {
        return filesize($this->file);
    }

    /**
     * actual reading of length starting at given offset
     *
     * @param  int  $offset
     * @param  int  $count
     */
    protected function doRead($offset, $count)
    {
        $this->open();
        return stream_get_contents($this->handle, $count, $offset);
    }

    /**
     * actual writing of data with specified length at given offset
     *
     * @param   string  $data
     * @param   int     $offset
     * @param   int     $length
     */
    protected function doWrite($data, $offset, $length)
    {
        // Nothing
    }

    /**
     * returns actual content
     *
     * @return  string
     */
    public function content()
    {
        return stream_get_contents($this->handle, null, 0);
    }

    public function truncate($size)
    {
        return false;
    }

    public function readUntilEnd()
    {
        return stream_get_contents($this->handle, null, $this->bytesRead());
    }
}
