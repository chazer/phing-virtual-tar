<?php
/**
 * bootstrap.php
 *
 * @author: chazer
 * @created: 15.02.16 4:17
 */

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    $vendors = __DIR__ . '/../vendor';
} elseif (is_file(__DIR__ . '/../../vendor/autoload.php')) {
    $vendors = __DIR__ . '/../../vendor';
} elseif (is_file(__DIR__ . '/../../../vendor/autoload.php')) {
    $vendors = __DIR__ . '/../../../vendor';
} else {
    $vendors = __DIR__ . '/../../../../vendor';
}

$backupIncludePath = get_include_path();

set_include_path(
    __DIR__ . PATH_SEPARATOR .
    $vendors . "/mikey179/vfsStream/src/main/php" . PATH_SEPARATOR .
    $backupIncludePath);

require_once 'org/bovigo/vfs/vfsStream.php';
require_once 'org/bovigo/vfs/vfsStreamContainer.php';
require_once 'org/bovigo/vfs/vfsStreamContent.php';
require_once 'org/bovigo/vfs/vfsStreamAbstractContent.php';
require_once 'org/bovigo/vfs/vfsStreamDirectory.php';
require_once 'org/bovigo/vfs/vfsStreamWrapper.php';
require_once 'org/bovigo/vfs/Quota.php';
require_once 'org/bovigo/vfs/vfsStreamFile.php';
require_once 'org/bovigo/vfs/content/FileContent.php';
require_once 'org/bovigo/vfs/content/SeekableFileContent.php';
require_once 'org/bovigo/vfs/content/StringBasedFileContent.php';
require_once 'PhingVirtualTar/ReadOnlyFileContent.php';

set_include_path($backupIncludePath);
