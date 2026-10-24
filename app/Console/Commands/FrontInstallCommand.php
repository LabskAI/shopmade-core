<?php

namespace App\Console\Commands;

use App\Core\Front\FrontContentHistory;
use App\Core\Front\FrontKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('front:install {company? : Defaults to AGENTS_COMPANY}')]
#[Description('Copy the starter site into the content folder when the company has no content yet. Existing content is never touched.')]
class FrontInstallCommand extends Command
{
    public function handle(FrontKit $kit, FrontContentHistory $history): int
    {
        $company = (string) ($this->argument('company') ?: config('agents.company'));
        $target = $kit->directory($company);

        if (is_dir($target) && (scandir($target) ?: []) !== ['.', '..']) {
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

        File::ensureDirectoryExists($target);

        if (! File::copyDirectory($starter, $target)) {
            File::deleteDirectory($target);
            $this->error("Could not copy the starter content from {$starter} into {$target}; nothing installed.");

            return self::FAILURE;
        }

        $history->commit($company, 'Starter content from '.basename($starter));

        $this->info("Copied the starter content from {$starter} into {$target}.");
        if ($company !== 'demo') {
            $this->warn("If this install already had a live site under {$starterRoot}/{$company}, it is not in {$target}: mount that volume at the content folder instead (see README, Upgrading).");
        }

        return self::SUCCESS;
    }
}
