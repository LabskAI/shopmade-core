<?php

namespace App\Http\Controllers\Site;

use App\Core\Agents\AgentRefusedException;
use App\Http\Controllers\Controller;

/**
 * Shared by the website editor screens (Admin → Website): how a refusal from the editor is reported back to the form
 * and the flash message after a save.
 */
abstract class FrontEditorController extends Controller
{
    protected function refusal(AgentRefusedException $exception): string
    {
        return trim($exception->getMessage().' '.$exception->hint);
    }

    /**
     * The flash message after a save, with the lint errors of the saved file (or of the whole front).
     *
     * @param  array{saved: bool, path: string, lint?: array{errors: list<array{rule: string, file: string, message: string}>}}  $result
     */
    protected function savedMessage(array $result, bool $wholeFront = false): string
    {
        if (! $result['saved']) {
            return 'Nothing changed, so nothing was saved.';
        }

        $errors = array_filter(
            $result['lint']['errors'] ?? [],
            fn (array $issue): bool => $wholeFront || $issue['file'] === $result['path'],
        );

        return $errors === []
            ? 'Saved. The change is live.'
            : sprintf('Saved and live, with %d lint %s to fix.', count($errors), count($errors) === 1 ? 'error' : 'errors');
    }
}
