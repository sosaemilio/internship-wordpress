<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use StellarWP\PluginFramework\Exceptions\FilesystemException;
use StellarWP\PluginFramework\Support\Filesystem;

/**
 * Support trait for tests that need to create temporary files and directories.
 *
 * Disable checks for WP_Filesystem methods that don't make sense in a testing environment:
 *
 * @phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen
 * @phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fclose
 */
trait CreatesTempFiles
{
    /**
     * @var Array<string> Temporary directories.
     */
    private $tempDirs = [];

    /**
     * @var Array<string,resource> Temporary files, keyed by their system paths.
     */
    private $tempFiles = [];

    /**
     * Clean up temp files after each test.
     *
     * @throws FilesystemException If attempting to delete a directory outside of the system temp directory.
     *
     * @after
     */
    protected function cleanUpTemporaryFiles()
    {
        foreach ($this->tempFiles as $filepath => $resource) {
            if (is_resource($resource)) {
                fclose($resource);
            }

            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        foreach ($this->tempDirs as $dir) {
            // Safeguard the filesystem, only delete directories in the tmp dir.
            if (0 !== mb_strpos($dir, sys_get_temp_dir())) {
                throw new FilesystemException(sprintf(
                    'Refusing to delete directory "%s", which is outside of the system temp directory.',
                    $dir
                ));
            }

            Filesystem::init()->delete($dir, true, 'd');
        }
    }

    /**
     * Create a new temporary directory.
     *
     * @param string $name Optional. The directory name (or nested path) to create. If left empty,
     *                     a directory name will be created. Default is empty.
     *
     * @throws FilesystemException If unable to create a temporary directory.
     *
     * @return string The absolute system path to the newly-created, temporary directory.
     */
    protected function createTempDirectory($name = '')
    {
        if (empty($name)) {
            $name = uniqid('stellarwp-dir-');
        }

        $directories = array_filter(explode(DIRECTORY_SEPARATOR, $name));
        $path        = untrailingslashit(sys_get_temp_dir());
        $created     = [];

        foreach ($directories as $dir) {
            $path .= DIRECTORY_SEPARATOR . $dir;

            if (file_exists($path)) {
                if (! is_dir($path)) {
                    throw new FilesystemException(sprintf(
                        'Unable to create directory %s, as parent %s exists but is not a file!',
                        $name,
                        $path
                    ));
                }

                continue;
            }

            if (! mkdir($path)) {
                throw new FilesystemException(sprintf('Unable to create temp directory %s', $path));
            }

            $created[] = $path;
        }

        rsort($created);

        $this->tempDirs += $created;

        return $path;
    }

    /**
     * Create a new temporary file.
     *
     * @param string $filepath Optional. The absolute system filepath where the file should be created.
     *                         If left empty, a file will be created in the system tmp directory.
     *                         Default is empty.
     *
     * @throws FilesystemException If the temp file cannot be created (or would overwrite an existing,
     *                             non-temporary file).
     *
     * @return resource
     */
    protected function createTempFile($filepath = '')
    {
        if ($filepath && file_exists($filepath)) {
            throw new FilesystemException(sprintf(
                'Refusing to overwrite existing file %s with a temporary file.',
                $filepath
            ));
        }

        if ($filepath) {
            if (! $fh = fopen($filepath, 'w+b')) {
                throw new FilesystemException(sprintf(
                    'Unable to create temporary file at %s',
                    $filepath
                ));
            }
        } else {
            if (! $fh = tmpfile()) {
                throw new FilesystemException('Unable to create a new temp file via tmpfile().');
            }

            $filepath = stream_get_meta_data($fh)['uri'];
        }

        $this->tempFiles[$filepath] = $fh;

        return $fh;
    }
}
