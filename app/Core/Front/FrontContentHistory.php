<?php

namespace App\Core\Front;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * The content folder (agents.paths.fronts, one sub-folder per company) is its own git repository, kept apart from the
 * code: every website save is a commit there, so a client's edits have a full history and undo and a code deploy
 * never touches them. A failed commit is reported and never fails the save; front_revisions still records it.
 */
class FrontContentHistory
{
    public function __construct(private FrontKit $kit) {}

    /**
     * Commits the given company's folder (or one file in it) with the message and author. While the folder has no
     * commit yet (a fresh repository, or an existing site just mounted) the whole company folder goes into the first
     * commit, so lang/, skills/ and assets/ are tracked from the start.
     */
    public function commit(string $company, string $message, ?string $author = null, ?string $file = null): void
    {
        try {
            $root = dirname($this->kit->directory($company));

            if (! is_dir($root.'/.git')) {
                $this->git($root, ['init', '--quiet']);
            }

            $hasCommits = $this->run($root, ['rev-parse', '--verify', '--quiet', 'HEAD'])->successful();
            $path = $company.($file !== null && $hasCommits ? '/'.$file : '');
            $this->git($root, ['add', '-A', '--', $path]);

            if ($this->run($root, ['diff', '--cached', '--quiet', '--', $path])->successful()) {
                return;
            }

            $name = $author !== null && trim($author) !== '' ? $author : 'Shopmade';
            $this->git($root, ['-c', 'user.name='.$name, '-c', 'user.email=shopmade@localhost', 'commit', '--quiet', '-m', $message, '--', $path]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(string $root, array $arguments): void
    {
        $result = $this->run($root, $arguments);

        if ($result->failed()) {
            throw new RuntimeException('git '.$arguments[0].' failed in the content folder: '.trim($result->errorOutput()));
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(string $root, array $arguments): ProcessResult
    {
        return Process::path($root)->timeout(30)->run(['git', '-c', 'safe.directory=*', ...$arguments]);
    }
}
