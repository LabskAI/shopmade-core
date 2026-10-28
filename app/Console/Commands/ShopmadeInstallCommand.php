<?php

namespace App\Console\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Composer;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

#[Signature('shopmade:install {--packages= : Comma-separated short names to install without asking, e.g. jobs,invoicing} {--none : Install no packages} {--local : Set APP_ENV=local and APP_DEBUG=true in .env without asking} {--no-dev : Leave the dev dependencies out (image builds)}')]
#[Description('Pick the public Shopmade packages to include and install them with Composer. Runs after composer create-project; run it again to add more.')]
class ShopmadeInstallCommand extends Command
{
    public function handle(Composer $composer): int
    {
        /** @var Collection<string, array{package: string, repository: string, label: string, description: string}> $available */
        $available = collect(config('shopmade.packages', []))
            ->reject(fn (array $entry): bool => InstalledVersions::isInstalled($entry['package']));

        $selected = $this->selectPackages($available);

        if ($selected === null) {
            return self::FAILURE;
        }

        if ($selected !== []) {
            $entries = array_map(fn (string $key): array => $available[$key], $selected);
            $this->addRepositories($entries);
            $this->info('Installing '.implode(', ', array_column($entries, 'package')).' ...');

            // The tagged release once there is one, the main branch until then.
            $requirements = array_map(fn (array $entry): string => "{$entry['package']}:^0.1 || dev-main", $entries);

            if ($this->option('no-dev')) {
                $requirements[] = '--update-no-dev';
            }

            $installed = $composer->requirePackages(
                $requirements,
                output: $this->output,
                composerBinary: getenv('COMPOSER_BINARY') ?: null,
            );

            if (! $installed) {
                $this->error('Composer could not install the packages. Fix the error above and run php artisan shopmade:install again.');

                return self::FAILURE;
            }
        }

        $this->configureLocalEnvironment();
        $this->printNextSteps();

        return self::SUCCESS;
    }

    /**
     * Point Composer at each package's GitHub repo, so installs don't depend on Packagist.
     *
     * @param  list<array{package: string, repository: string, label: string, description: string}>  $entries
     */
    private function addRepositories(array $entries): void
    {
        $path = base_path('composer.json');
        $composerJson = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $repositories = $composerJson['repositories'] ?? [];
        $known = array_column($repositories, 'url');

        foreach ($entries as $entry) {
            if (! in_array($entry['repository'], $known, true)) {
                $repositories[str_replace('/', '-', $entry['package'])] = ['type' => 'vcs', 'url' => $entry['repository']];
            }
        }

        if ($repositories === ($composerJson['repositories'] ?? [])) {
            return;
        }

        $composerJson['repositories'] = $repositories;
        file_put_contents($path, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    /**
     * The short names to install, [] for none, or null when --packages names one we don't offer.
     *
     * @param  Collection<string, array{package: string, repository: string, label: string, description: string}>  $available
     * @return list<string>|null
     */
    private function selectPackages(Collection $available): ?array
    {
        if ($this->option('none')) {
            return [];
        }

        if ($this->option('packages') !== null) {
            $requested = collect(explode(',', (string) $this->option('packages')))
                ->map(fn (string $key): string => strtolower(trim($key)))
                ->filter()
                ->unique()
                ->values();

            $unknown = $requested->reject(fn (string $key): bool => array_key_exists($key, config('shopmade.packages', [])));

            if ($unknown->isNotEmpty()) {
                $this->error('Unknown package: '.$unknown->implode(', ').'. Choose from: '.implode(', ', array_keys(config('shopmade.packages', []))).'.');

                return null;
            }

            return $requested->filter(fn (string $key): bool => $available->has($key))->all();
        }

        if ($available->isEmpty() || ! $this->input->isInteractive()) {
            return [];
        }

        return array_values(multiselect(
            label: 'Which packages do you want to include?',
            options: $available->map(fn (array $entry): string => "{$entry['label']}: {$entry['description']}")->all(),
            scroll: 10,
            hint: 'Space to select, Enter to confirm. You can add more later with php artisan shopmade:install.',
        ));
    }

    /**
     * A create-project checkout is usually someone's laptop: local mode shows errors and lets Claude Code's
     * localhost OAuth callback through. Servers use install.sh or the production image instead.
     */
    private function configureLocalEnvironment(): void
    {
        $envPath = $this->laravel->environmentFilePath();

        if (! is_file($envPath) || ! str_contains((string) file_get_contents($envPath), 'APP_ENV=production')) {
            return;
        }

        $local = $this->option('local')
            || ($this->input->isInteractive() && confirm('Run this install locally for development (APP_ENV=local)?', default: true));

        if (! $local) {
            return;
        }

        $env = (string) file_get_contents($envPath);
        $env = preg_replace('/^APP_ENV=.*$/m', 'APP_ENV=local', $env);
        $env = preg_replace('/^APP_DEBUG=.*$/m', 'APP_DEBUG=true', $env);
        file_put_contents($envPath, $env);

        $this->info('Set APP_ENV=local and APP_DEBUG=true in .env.');
    }

    private function printNextSteps(): void
    {
        $url = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->line('<options=bold>Next</>');
        $this->line('  docker compose up -d --build');
        $this->line('  docker compose exec app php artisan user:create-owner you@example.com "Your Name"');
        $this->line("  Sign in at {$url}/login");
        $this->line("  Connect Claude Code: claude mcp add --transport http shopmade {$url}/mcp/agents");
        $this->newLine();
    }
}
