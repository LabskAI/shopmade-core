<?php

namespace App\Http\Controllers\Site;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FactsController extends FrontEditorController
{
    public function edit(FrontEditor $editor): View
    {
        $facts = $editor->facts();
        $languages = $editor->languages();

        return view('site.facts.edit', [
            'facts' => $facts,
            'languages' => $languages,
            'services' => $this->serviceRows($facts, $languages),
            'lint' => $editor->lint(),
        ]);
    }

    public function update(Request $request, FrontEditor $editor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:200'],
            'languages' => ['required', 'string', 'max:100', 'regex:/^\s*[a-z]{2}(?:[\s,]+[a-z]{2})*[\s,]*\z/'],
            'cities' => ['nullable', 'string', 'max:5000'],
            'hours' => ['nullable', 'string', 'max:2000'],
            'never_write' => ['nullable', 'string', 'max:10000'],
            'services' => ['nullable', 'array', 'max:100'],
            'services.*.slug' => ['nullable', 'string', 'max:80', 'regex:/^'.FrontKit::SLUG_PATTERN.'\z/'],
            'services.*.name' => ['nullable', 'array'],
            'services.*.name.*' => ['nullable', 'string', 'max:200'],
        ], [
            'languages.regex' => 'List two-letter language codes, the default first, for example: en, es.',
            'services.*.slug.regex' => 'A service slug uses lowercase letters, digits and dashes, for example house-cleaning.',
        ]);

        $languages = array_values(array_unique(preg_split('/[\s,]+/', trim($data['languages'], " \t\n\r,")) ?: []));

        try {
            $result = $editor->saveFacts([
                'name' => trim($data['name']),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'languages' => $languages,
                'cities' => $this->lines($data['cities'] ?? null),
                'services' => $this->services($data['services'] ?? [], $languages),
                'hours' => $this->lines($data['hours'] ?? null),
                'never_write' => $this->lines($data['never_write'] ?? null),
            ], Actor::forUser($request->user()));
        } catch (AgentRefusedException $exception) {
            return back()->withInput()->withErrors(['facts' => $this->refusal($exception)]);
        }

        return redirect()->route('site.facts.edit')->with('status', $this->savedMessage($result, wholeFront: true));
    }

    /**
     * One row per service for the form: slug and a name per site language (a plain-text name fills the default language).
     *
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $languages
     * @return list<array{slug: string, names: array<string, string>}>
     */
    private function serviceRows(array $facts, array $languages): array
    {
        $rows = [];

        foreach ((array) ($facts['services'] ?? []) as $service) {
            if (! is_array($service) || ! is_string($service['slug'] ?? null)) {
                continue;
            }

            $name = $service['name'] ?? null;
            $names = is_array($name) ? $name : [$languages[0] => $name];

            $rows[] = [
                'slug' => $service['slug'],
                'names' => array_map(fn (mixed $value): string => is_string($value) ? $value : '', array_intersect_key($names, array_flip($languages))),
            ];
        }

        return $rows;
    }

    /**
     * Rows without a slug or a name are dropped. A name given only in the default language is stored as plain text.
     *
     * @param  array<int, array{slug?: ?string, name?: array<string, ?string>}>  $rows
     * @param  list<string>  $languages
     * @return list<array{slug: string, name: string|array<string, string>}>
     */
    private function services(array $rows, array $languages): array
    {
        $services = [];

        foreach ($rows as $index => $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $names = array_filter(
                array_map(fn (mixed $name): string => trim((string) $name), array_intersect_key((array) ($row['name'] ?? []), array_flip($languages))),
                fn (string $name): bool => $name !== '',
            );

            if ($slug === '' && $names === []) {
                continue;
            }

            if ($slug === '' || $names === []) {
                throw ValidationException::withMessages([
                    "services.{$index}" => $slug === '' ? 'Each service needs a slug, for example house-cleaning.' : "Service {$slug} needs a name.",
                ]);
            }

            if (isset($services[$slug])) {
                throw ValidationException::withMessages(["services.{$index}" => "Two services use the slug {$slug}."]);
            }

            $ordered = array_intersect_key(array_merge(array_flip($languages), $names), $names);

            $services[$slug] = [
                'slug' => $slug,
                'name' => array_keys($ordered) === [$languages[0]] ? $ordered[$languages[0]] : $ordered,
            ];
        }

        return array_values($services);
    }

    /**
     * @return list<string>
     */
    private function lines(?string $text): array
    {
        $lines = array_map('trim', preg_split('/\R/', (string) $text) ?: []);

        return array_values(array_unique(array_filter($lines, fn (string $line): bool => $line !== '')));
    }
}
