<?php
/**
 * TarTask.php
 *
 * @author: chazer
 * @created: 12.02.16 15:18
 */

namespace PhingVirtualTar;

use Archive_Tar;
use BuildException;
use FileSet;
use IOException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PEAR;
use PhingFile;
use Project;
use TarFileSet as PhingTarFileSet;
use TarTask as PhingTarTask;


/**
 * Creates a tar archive using PEAR Archive_Tar.
 *
 * @author    Hans Lellelid <hans@xmpl.org> (Phing)
 * @author    Stefano Mazzocchi <stefano@apache.org> (Ant)
 * @author    Stefan Bodewig <stefan.bodewig@epost.de> (Ant)
 * @author    Magesh Umasankar
 *
 * @package   phing.tasks.ext
 */
class TarTask extends PhingTarTask
{
    /** @var PhingFile */
    private $tarFile;

    /** @var PhingFile */
    private $baseDir;

    private $includeEmpty = true; // Whether to include empty dirs in the TAR

    /**
     * @var PhingTarFileSet[]
     */
    private $filesets = array();

    /**
     * Compression mode.  Available options "gzip", "bzip2", "none" (null).
     */
    private $compression = null;

    /**
     * File path prefix in the tar archive
     *
     * @var string
     */
    private $prefix = null;

    /**
     * Add a new fileset
     * @return FileSet
     */
    public function createTarFileSet()
    {
        $this->fileset = new TarFileSet();
        $this->filesets[] = $this->fileset;

        return $this->fileset;
    }

    /**
     * Add a new fileset.  Alias to createTarFileSet() for backwards compatibility.
     * @return FileSet
     * @see createTarFileSet()
     */
    public function createFileSet()
    {
        $this->fileset = new TarFileSet();
        $this->filesets[] = $this->fileset;

        return $this->fileset;
    }

    /**
     * Set is the name/location of where to create the tar file.
     * @param PhingFile $destFile The output of the tar
     */
    public function setDestFile(PhingFile $destFile)
    {
        $this->tarFile = $destFile;
    }

    /**
     * This is the base directory to look in for things to tar.
     * @param PhingFile $baseDir
     */
    public function setBasedir(PhingFile $baseDir)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * Set the include empty dirs flag.
     *
     * @param  boolean $bool Flag if empty dirs should be tarred too
     *
     * @return void
     */
    public function setIncludeEmptyDirs($bool)
    {
        $this->includeEmpty = (boolean) $bool;
    }

    /**
     * Set compression method.
     * Allowable values are
     * <ul>
     * <li>  none - no compression
     * <li>  gzip - Gzip compression
     * <li>  bzip2 - Bzip2 compression
     * </ul>
     * @param string $mode
     */
    public function setCompression($mode)
    {
        switch ($mode) {
            case "gzip":
                $this->compression = "gz";
                break;
            case "bzip2":
                $this->compression = "bz2";
                break;
            case "none":
                $this->compression = null;
                break;
            default:
                $this->log("Ignoring unknown compression mode: " . $mode, Project::MSG_WARN);
                $this->compression = null;
        }
    }

    /**
     * Sets the file path prefix for file in the tar file.
     *
     * @param string $prefix Prefix
     *
     * @return void
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * do the work
     * @throws BuildException
     */
    public function main()
    {
        if ($this->tarFile === null) {
            throw new BuildException("tarfile attribute must be set!", $this->getLocation());
        }

        if ($this->tarFile->exists() && $this->tarFile->isDirectory()) {
            throw new BuildException("tarfile is a directory!", $this->getLocation());
        }

        if ($this->tarFile->exists() && !$this->tarFile->canWrite()) {
            throw new BuildException("Can not write to the specified tarfile!", $this->getLocation());
        }

        // shouldn't need to clone, since the entries in filesets
        // themselves won't be modified -- only elements will be added
        $savedFileSets = $this->filesets;

        try {
            if ($this->baseDir !== null) {
                if (!$this->baseDir->exists()) {
                    throw new BuildException("basedir '" . (string) $this->baseDir . "' does not exist!", $this->getLocation());
                }
                if (empty($this->filesets)) { // if there weren't any explicit filesets specivied, then
                    // create a default, all-inclusive fileset using the specified basedir.
                    $mainFileSet = new TarFileSet($this->fileset);
                    $mainFileSet->setDir($this->baseDir);
                    $this->filesets[] = $mainFileSet;
                }
            }

            if (empty($this->filesets)) {
                throw new BuildException("You must supply either a basedir "
                    . "attribute or some nested filesets.",
                    $this->getLocation());
            }

            // check if tar is out of date with respect to each fileset
            if ($this->tarFile->exists() && $this->isArchiveUpToDate()) {
                $this->log("Nothing to do: " . $this->tarFile->__toString() . " is up to date.", Project::MSG_INFO);
                return;
            }

            $this->log("Building tar: " . $this->tarFile->__toString(), Project::MSG_INFO);

            $tar = new Archive_Tar($this->tarFile->getAbsolutePath(), $this->compression);
            $pear = new PEAR();

            if ($pear->isError($tar->error_object)) {
                throw new BuildException($tar->error_object->getMessage());
            }

            foreach ($this->filesets as $fs) {
                $files = $fs->getFiles($this->project, $this->includeEmpty);
                if (count($files) > 1 && strlen($fs->getFullpath()) > 0) {
                    throw new BuildException("fullpath attribute may only "
                        . "be specified for "
                        . "filesets that specify a "
                        . "single file.");
                }
                $fsBasedir = $fs->getDir($this->project);
                $filesToTar = array();
                for ($i = 0, $fcount = count($files); $i < $fcount; $i++) {
                    $f = new PhingFile($fsBasedir, $files[$i]);
                    $filesToTar[] = $f->getAbsolutePath();
                    $this->log("Adding file " . $f->getPath() . " to archive.", Project::MSG_VERBOSE);
                }
                $options = array(
                    'addPrefix' => $this->prefix,
                    'removePrefix' => $fsBasedir->getAbsolutePath(),
                );
                if ($fs instanceof PhingTarFileSet) {
                    $options['setMode'] = $fs->getMode();
                }
                $this->addFiles($tar, $filesToTar, $options);

                if ($pear->isError($tar->error_object)) {
                    throw new BuildException($tar->error_object->getMessage());
                }
            }


        } catch (IOException $ioe) {
            $msg = "Problem creating TAR: " . $ioe->getMessage();
            $this->filesets = $savedFileSets;
            throw new BuildException($msg, $ioe, $this->getLocation());
        }

        $this->filesets = $savedFileSets;
    }

    /**
     * @return array
     * @throws BuildException
     */
    private function isArchiveUpToDate()
    {
        foreach ($this->filesets as $fs) {
            $files = $fs->getFiles($this->project, $this->includeEmpty);
            if (!$this->areFilesUpToDate($files, $fs->getDir($this->project))) {
                return false;
            }
            for ($i = 0, $fcount = count($files); $i < $fcount; $i++) {
                if ($this->tarFile->equals(new PhingFile($fs->getDir($this->project), $files[$i]))) {
                    throw new BuildException("A tar file cannot include itself", $this->getLocation());
                }
            }
        }
        return true;
    }

    /**
     * @param Archive_Tar $tar
     * @param array $files
     * @param array $options
     */
    protected function addFiles($tar, $files, $options = array())
    {
        $addPrefix = isset($options['addPrefix']) ? $options['addPrefix'] : '';
        $removePrefix = isset($options['removePrefix']) ? $options['removePrefix'] : '';

        $vfs = new TarVirtualFS();

        if (isset($options['setMode'])) {
            $mode = $options['setMode'];

            $removePrefix = $this->normalizePath($removePrefix, false);
            if ($removePrefix != '' && substr($removePrefix, -1) != '/') {
                $removePrefix .= '/';
            }

            $removePrefixSize = strlen($removePrefix);

            foreach ($files as $i => $file) {
                $realPath = $file;
                $file = $this->normalizePath($file, false);
                if ($removePrefixSize > 0 && strpos($file, $removePrefix) === 0) {
                    $file = substr($file, $removePrefixSize);
                }

                $files[$i] = $vfs->addFile($realPath, $file, $mode);
            }

            foreach ($files as $i => $file) {
                echo $files[$i] . ': ';
                echo (file_exists($files[$i]) ? ' exists' : 'not exists') . ' ';
                echo decoct(stat($files[$i])['mode']) . ' ';
                echo file_get_contents($files[$i]) . ' ';
                echo PHP_EOL;
            }

            $removePrefix = $vfs->getPrefix();
        }

        $tar->addModify($files, $addPrefix, $removePrefix);

        $vfs->unregister();
    }

    /**
     * Convert path
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath($path)
    {
        if (defined('OS_WINDOWS') && OS_WINDOWS) {
            // Change potential windows directory separator
            // Identical to Archive_Tar logic
            if ((strpos($path, '\\') > 0) || (substr($path, 0, 1) == '\\')) {
                $path = strtr($path, '\\', '/');
            }
        }
        return $path;
    }
}

/**
 * This is a FileSet with the option to specify permissions.
 *
 * Permissions are currently not implemented by PEAR Archive_Tar,
 * but hopefully they will be in the future.
 *
 * @package   phing.tasks.ext
 */
class TarFileSet extends PhingTarFileSet
{
    private $mode = 0100644;

    /**
     * A 3 digit octal string, specify the user, group and
     * other modes in the standard Unix fashion;
     * optional, default=0644
     * @param string $octalString
     */
    public function setMode($octalString)
    {
        $octal = octdec($octalString);
        $this->mode = 0100000 | $octal;
    }

    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }
}

class TarVirtualFS
{
    private $prefix;

    private $files = array();

    /** @var vfsStreamDirectory */
    private $root;

    public function register()
    {
    }

    public function unregister()
    {
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return vfsStream::SCHEME . '://' . $this->prefix;
    }

    public function __construct()
    {
        $this->prefix = md5(microtime(true));
        $this->root = vfsStream::setup($this->prefix);
    }

    public function virtualFiles(&$files, $mode)
    {
        foreach ($files as $i => $file) {
            $files[$i] = $this->addFile(false, $file, $mode);
        }
    }

    public function addFile($realPath, $file, $mode)
    {
        $this->files['file'] = array(
            'source' => $realPath,
            'mode' => $mode,
        );

        $dir = $this->root;
        while (false !== ($pos = strpos($file, '/', 1))) {
            $dirName = substr($file, 0, $pos);
            $dir = $dir->getChild($dirName) ? : vfsStream::newDirectory($dirName, 0777)->at($dir);
            $file = substr($file, $pos + 1);
        }
        $vfile = vfsStream::newFile($file, $mode)
            ->at($dir)
            ->setContent($realPath ? new ReadOnlyFileContent($realPath) : '');

        return $vfile->url();
    }
}
