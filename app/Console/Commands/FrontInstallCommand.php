<?php

namespace App\Console\Commands;

use App\Core\Front\FrontContentHistory;
use App\Core\Front\FrontKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('front:install {company? : Defaults to AGENTS_COMPANY} {--force : Replace existing content with the shipped front (fronts/<company>), after committing a snapshot of it}')]
#[Description('Copy the starter site into the content folder when the company has no content yet. Existing content is never touched unless --force is given.')]
class FrontInstallCommand extends Command
{
    public function handle(FrontKit $kit, FrontContentHistory $history): int
    {
        $company = (string) ($this->argument('company') ?: config('agents.company'));
        $target = $kit->directory($company);
        $hasContent = is_dir($target) && (scandir($target) ?: []) !== ['.', '..'];
        $force = (bool) $this->option('force');

        if ($hasContent && ! $force) {
            $history->commit($company, 'Snapshot of the existing content');
            $this->info("Content for {$company} is already in place; nothing copied.");

            return self::SUCCESS;
        }

        $starterRoot = rtrim((string) config('agents.paths.repo'), '/').'/'.trim((string) config('agents.paths.starter', 'fronts'), '/');
        $starter = is_dir($starterRoot.'/'.$company) ? $starterRoot.'/'.$company : $starterRoot.'/demo';

        if (! is_dir($starter)) {
            $this->error("No starter content at {$starter}.");

            return self::FAILURE;
        }

        if ($hasContent) {
            if (basename($starter) !== $company) {
                $this->error("There is no shipped front at {$starterRoot}/{$company}; the existing content was left as it is.");

                return self::FAILURE;
            }

            if (is_link($target) || realpath($target) === realpath($starter)) {
                $this->error("{$target} is a symlink or the shipped front itself; nothing replaced.");

                return self::FAILURE;
            }

            if (! $history->commit($company, 'Snapshot before front:install --force')) {
                $this->error("Could not commit a snapshot of the existing content of {$company} to the content history; nothing was replaced or deleted. Fix the content folder's git repository and run the command again.");

                return self::FAILURE;
            }

            File::deleteDirectory($target);
        }

        File::ensureDirectoryExists($target);

        if (! File::copyDirectory($starter, $target)) {
            File::deleteDirectory($target);
            $this->error("Could not copy the starter content from {$starter} into {$target}; nothing installed."
                .($hasContent ? ' The previous content is in the content history (the snapshot commit).' : ''));

            return self::FAILURE;
        }

        $history->commit($company, ($hasContent ? 'Replaced with the shipped front from ' : 'Starter content from ').basename($starter));

        if ($hasContent) {
            $this->info("Replaced the content of {$company} with {$starter}. The previous content is the commit before this one in the content history.");

            return self::SUCCESS;
        }

        $this->info("Copied the starter content from {$starter} into {$target}.");
        if ($company !== 'demo') {
            $this->warn("If this install already had a live site under {$starterRoot}/{$company}, it is not in {$target}: mount that volume at the content folder instead (see README, Upgrading).");
        }

        return self::SUCCESS;
    }
}
