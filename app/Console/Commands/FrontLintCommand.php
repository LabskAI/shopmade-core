<?php

namespace App\Console\Commands;

use App\Core\Front\FrontLinter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('front:lint {company : The company whose fronts/<company> folder is checked}')]
#[Description('Lint a public front: never-write phrases, cities and services, prices, frontmatter, EN/ES parity, JSON-LD claims, discovery files.')]
class FrontLintCommand extends Command
{
    public function handle(FrontLinter $linter): int
    {
        $company = (string) $this->argument('company');
        $report = $linter->lint($company);

        foreach ($report['errors'] as $issue) {
            $this->line(sprintf('ERROR [%s] %s: %s', $issue['rule'], $issue['file'], $issue['message']));
        }

        foreach ($report['warnings'] as $issue) {
            $this->line(sprintf('WARN  [%s] %s: %s', $issue['rule'], $issue['file'], $issue['message']));
        }

        $summary = sprintf(
            'front:lint %s: %d error(s), %d warning(s).',
            $company,
            count($report['errors']),
            count($report['warnings']),
        );

        if (! $report['ok']) {
            $this->error($summary);

            return self::FAILURE;
        }

        $this->info($summary);

        return self::SUCCESS;
    }
}
