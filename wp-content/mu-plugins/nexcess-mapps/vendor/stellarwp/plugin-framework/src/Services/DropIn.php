<?php

namespace StellarWP\PluginFramework\Services;

use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Exceptions\DropInException;
use StellarWP\PluginFramework\Exceptions\InvalidDropInException;
use StellarWP\PluginFramework\Support\Filesystem;

class DropIn
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Construct a new instance of the DropIn service.
     *
     * @param \Psr\Log\LoggerInterface $logger The logger instance.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Clean up existing drop-in files.
     *
     * This will remove drop-ins that meet any of the following criteria:
     *
     * - Is a broken symlink (e.g. its target no longer exists).
     * - Is an empty file, containing no executable PHP code.
     *
     * @return void
     */
    public function clean()
    {
        $fs = Filesystem::init();

        foreach (_get_dropins() as $dropin => $description) {
            $path = WP_CONTENT_DIR . '/' . $dropin;

            // Empty drop-in files.
            if ($fs->is_readable($path) && 0 === $fs->size($path)) {
                $this->logger->notice(sprintf('Removing empty drop-in file %1$s.', $dropin));
                $fs->delete($path, false, 'f');
                continue;
            }

            // Broken symlinks.
            if (Filesystem::isBrokenSymlink($path)) {
                $this->logger->notice(sprintf(
                    'Removing %1$s, as it points to a non-existent path %2$s.',
                    $dropin,
                    readlink($path)
                ));
                $fs->delete($path, false, 'f');
                continue;
            }
        }
    }

    /**
     * Determine whether or not a given drop-in exists.
     *
     * @param string $dropin The name of the drop-in file.
     *
     * @throws InvalidDropInException If the requested drop-in is not a drop-in file recognized by WordPress.
     *
     * @return bool True if the drop-in is valid and exists, false otherwise.
     */
    public function exists($dropin)
    {
        return is_readable($this->getPath($dropin));
    }

    /**
     * Get the system path to a given drop-in file.
     *
     * This method will also validate that the requested drop-in name is valid, but *not* whether or
     * not the file exists.
     *
     * @param string $dropin The drop-in filename. This may or may not include the ".php" extension.
     *
     * @throws InvalidDropInException If the requested drop-in is not a drop-in file recognized by WordPress.
     *
     * @return string The system path to the drop-in file.
     */
    public function getPath($dropin)
    {
        // Ensure we have just the filename + extension.
        $dropin = basename($dropin, '.php') . '.php';

        if (! $this->isValid($dropin)) {
            throw new InvalidDropInException(sprintf('"%s" is not a valid WordPress drop-in', $dropin));
        }

        return WP_CONTENT_DIR . '/' . $dropin;
    }

    /**
     * Symlink a drop-in into place.
     *
     * @param string $dropin The valid drop-in name, {@see _get_dropins()}.
     * @param string $source The system path for the drop-in file to be symlinked.
     * @param bool   $force  Optional. If true, overwrite an existing drop-in if one exists.
     *                       Default is false.
     *
     * @throws DropInException If the $source file doesn't exist.
     * @throws DropInException If a regular drop-in file exists and --force is not used.
     *
     * @return bool True if the symlink was made, false otherwise.
     */
    public function install($dropin, $source, $force = false)
    {
        $target = $this->getPath($dropin);

        // Ensure the $source file exists.
        if (! file_exists($source)) {
            throw new DropInException(sprintf('Source file %s does not exist', $source));
        }

        // Verify the target isn't already present.
        if (file_exists($target)) {
            if ($force) {
                unlink($target);
            } else {
                if (is_link($target)) {
                    return readlink($target) === $source;
                }

                throw new DropInException(sprintf(
                    'Refusing to overwrite existing regular drop-in file %1$s with symlink to %2$s',
                    $target,
                    $source
                ));
            }
        }

        // If it's a broken symlink, clean it up.
        if (Filesystem::isBrokenSymlink($target)) {
            unlink($target);
        }

        return symlink($source, $target);
    }

    /**
     * Check whether or not the given drop-in name is a valid, based on {@see _get_dropins()}.
     *
     * @param string $dropin The drop-in filename.
     *
     * @return bool True if the drop-in name is valid, false otherwise.
     */
    public function isValid($dropin)
    {
        return array_key_exists($dropin, _get_dropins());
    }

    /**
     * Remove an existing drop-in file.
     *
     * By default, this method will not remove regular files unless $force is true.
     *
     * Additionally, the optional $source parameter can be used to verify that a symlink points to
     * the given path; if not, the symlink will not be removed.
     *
     * @param string  $dropin The drop-in name.
     * @param ?string $source Optional. When present, only remove the drop-in if it's a symlink to
     *                        this filepath. Default is null.
     * @param bool    $force  Optional. Force the removal of a file, even if it's not a symlink.
     *                        Default is false.
     *
     * @throws DropInException If the drop-in file is a regular file and --force is not used.
     * @throws DropInException If $source is provided and the drop-in link's target does not match.
     *
     * @return bool True if the drop-in was removed, false otherwise.
     */
    public function remove($dropin, $source = null, $force = false)
    {
        $target    = $this->getPath($dropin);
        $is_broken = Filesystem::isBrokenSymlink($target);

        // The target doesn't exist, so there's nothing to do.
        if (! file_exists($target) && ! $is_broken) {
            return true;
        }

        // Don't remove normal files.
        if (file_exists($target) && ! is_link($target) && ! $force) {
            throw new DropInException(sprintf(
                'Refusing to remove regular drop-in file %1$s',
                $target
            ));
        }

        // If a $source is provided, validate the linked file.
        if (! empty($source) && ! $is_broken && is_link($target) && readlink($target) !== $source) {
            throw new DropInException(sprintf(
                'Failed asserting that the target of %1$s is %2$s (link resolves to %3$s)',
                $target,
                $source,
                readlink($target)
            ));
        }

        return unlink($target);
    }
}
