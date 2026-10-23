{{-- Front linter results (the rules of php artisan front:lint). Pass $issues: {errors, warnings}; $showFile to name each file. --}}
@php
    $showFile ??= false;
@endphp

@if ($issues['errors'] === [] && $issues['warnings'] === [])
    <p class="flex items-center gap-2 text-body text-ok-800" data-lint="clean"><i data-lucide="check" class="size-4"></i>No problems found.</p>
@else
    <ul class="space-y-2" data-lint="issues">
        @foreach ($issues['errors'] as $issue)
            <li class="border-l-2 border-accent bg-accent-100 px-3 py-2" data-lint-level="error">
                <p class="text-micro uppercase text-accent-800">Error · {{ str_replace('_', ' ', $issue['rule']) }}</p>
                @if ($showFile)
                    <p class="break-all font-mono text-label text-ink/70">{{ $issue['file'] }}</p>
                @endif
                <p class="break-words text-body text-ink">{{ $issue['message'] }}</p>
            </li>
        @endforeach
        @foreach ($issues['warnings'] as $issue)
            <li class="border-l-2 border-warn-600 bg-warn-100 px-3 py-2" data-lint-level="warning">
                <p class="text-micro uppercase text-warn-800">Warning · {{ str_replace('_', ' ', $issue['rule']) }}</p>
                @if ($showFile)
                    <p class="break-all font-mono text-label text-ink/70">{{ $issue['file'] }}</p>
                @endif
                <p class="break-words text-body text-ink">{{ $issue['message'] }}</p>
            </li>
        @endforeach
    </ul>
    @if ($issues['errors'] !== [])
        <p class="mt-3 text-label text-ink/55">Errors are problems visitors or search engines will notice. Fix them first.</p>
    @endif
@endif
