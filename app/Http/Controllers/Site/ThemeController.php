<?php

namespace App\Http\Controllers\Site;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontEditor;
use App\Core\Front\FrontThemeTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeController extends FrontEditorController
{
    public function edit(FrontEditor $editor, FrontThemeTokens $tokens): View
    {
        $values = $editor->themeTokens();

        return view('site.theme.edit', [
            'groups' => collect($values ?? [])
                ->map(fn (string $value, string $name): array => ['name' => $name, 'value' => $value, 'color' => $tokens->isColor($value)])
                ->groupBy(fn (array $token): string => $tokens->group($token['name'])),
            'hasTheme' => $values !== null,
            'liveUrl' => $editor->liveUrl(),
        ]);
    }

    public function update(Request $request, FrontEditor $editor): RedirectResponse
    {
        $data = $request->validate([
            'tokens' => ['required', 'array', 'max:200'],
            'tokens.*' => ['nullable', 'string', 'max:'.FrontThemeTokens::MAX_VALUE_LENGTH],
        ]);

        try {
            $result = $editor->saveTheme(
                array_map(fn (?string $value): string => (string) $value, $data['tokens']),
                Actor::forUser($request->user()),
            );
        } catch (AgentRefusedException $exception) {
            return back()->withInput()->withErrors(['tokens' => $this->refusal($exception)]);
        }

        return redirect()->route('site.theme.edit')->with('status', $this->savedMessage($result, wholeFront: true));
    }
}
