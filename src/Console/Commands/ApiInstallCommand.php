<?php

namespace Mk990\MkApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\InteractsWithComposerPackages;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'install:mkApi')]
class ApiInstallCommand extends Command
{
    use InteractsWithComposerPackages;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:mkApi
                    {--composer=global : Absolute path to the Composer binary which should be used to install packages}
                    {--force : Overwrite any existing file}
                    {--backup : Install Laravel Backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an API routes file and install Laravel Sanctum or Laravel Passport';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->installSwagger();

        $this->installJWT();
        $this->changeAuthConfigFile();

        if (file_exists($apiRoutesPath = $this->laravel->basePath('routes/api.php')) &&
            !$this->option('force')) {
            $this->components->error('API routes file already exists.');
        } else {
            $this->components->info('Published API routes file.');

            copy(__DIR__ . '/stubs/api-routes.stub', $apiRoutesPath);
            $this->uncommentApiRoutesFile();
        }

        if (!file_exists($fixerPath = $this->laravel->basePath('.php-cs-fixer.php'))) {
                copy(__DIR__ . '/stubs/phpfixer.stub', $fixerPath);
        }

        if ($this->option('backup')) {
            $this->installBackup();

            if (file_exists($apiRoutesPath = $this->laravel->basePath('config/backup.php')) &&
            !$this->option('force')) {
                $this->components->error('API routes file already exists.');
            } else {
                $this->components->info('Published backup config file.');
                copy(__DIR__ . '/stubs/backup.stub', $apiRoutesPath);
            }

            if (file_exists($consolePath = $this->laravel->basePath('routes/console.php'))) {
                $content = file_get_contents($consolePath);
                if (!str_contains($content, 'backup:run')) {
                    copy(__DIR__ . '/stubs/console.stub', $consolePath);
                }
            }
        }
    }

    /**
     * Uncomment the API routes file in the application bootstrap file.
     *
     * @return void
     */
    protected function uncommentApiRoutesFile()
    {
        $appBootstrapPath = $this->laravel->bootstrapPath('app.php');

        $content = file_get_contents($appBootstrapPath);

        if (str_contains($content, '// api: ')) {
            (new Filesystem())->replaceInFile(
                '// api: ',
                'api: ',
                $appBootstrapPath,
            );
        } elseif (str_contains($content, 'web: __DIR__.\'/../routes/web.php\',')) {
            (new Filesystem())->replaceInFile(
                'web: __DIR__.\'/../routes/web.php\',',
                'web: __DIR__.\'/../routes/web.php\',' . PHP_EOL . '        api: __DIR__.\'/../routes/api.php\',',
                $appBootstrapPath,
            );
        } else {
            $this->components->warn('Unable to automatically add API route definition to bootstrap file. API route file should be registered manually.');

            return;
        }
    }

    protected function changeAuthConfigFile()
    {
        $appBootstrapPath = $this->laravel->basePath('config/auth.php');

        $content = file_get_contents($appBootstrapPath);

        if (str_contains($content, "'AUTH_GUARD', 'web'")) {
            (new Filesystem())->replaceInFile(
                "'AUTH_GUARD', 'web'",
                "'AUTH_GUARD', 'api'",
                $appBootstrapPath,
            );
        } elseif (str_contains(
            $content,
            "'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],"
        )) {
            (new Filesystem())->replaceInFile(
                "'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],",
                "'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],",
                $appBootstrapPath,
            );
        } else {
            $this->components->warn('Unable to automatically add API route definition to bootstrap file. API route file should be registered manually.');

            return;
        }
    }

    protected function installSwagger()
    {
        $this->requireComposerPackages($this->option('composer'), [
            'darkaonline/l5-swagger:^8.6',
        ]);
    }

    protected function installJWT()
    {
        $this->requireComposerPackages($this->option('composer'), [
            'php-open-source-saver/jwt-auth:^2.7',
        ]);
    }

    protected function installBackup()
    {
        $this->requireComposerPackages($this->option('composer'), [
            'ext-zip:*',
            'spatie/laravel-backup:^9.1',
        ]);
    }
}
