<?php

namespace Mk990\MkApi\Console\Commands;

use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

trait InteractsWithComposerPackages
{
    /**
     * Installs the given Composer Packages into the application.
     *
     * @param  string  $composer
     * @param  array  $packages
     * @return bool
     */
    protected function requireComposerPackages(string $composer, array $packages, $dev = false)
    {
        if ($composer !== 'global') {
            $command = [$this->phpBinary(), $composer, 'require'];
        }

        if ($dev) {
            $command = array_merge(
                $command ?? ['composer', 'require', '--dev'],
                $packages,
            );
        } else {
            $command = array_merge(
                $command ?? ['composer', 'require'],
                $packages,
            );
        }

        return !(new Process($command, $this->laravel->basePath(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output) {
                $this->output->write($output);
            });
    }

    /**
     * Get the path to the appropriate PHP binary.
     *
     * @return string
     */
    protected function phpBinary()
    {
        return php_binary();
    }
}
