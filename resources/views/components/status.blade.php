@props(['state', 'label' => null])

@php
    /*
     * The one status map. Packages map their own states onto these with Hooks::status($state, like: …, label: …).
     */
    $map = [
        'draft' => 'bg-neutral-200 text-neutral-800',
        'info' => 'bg-info-100 text-info-800',
        'open' => 'bg-info-100 text-info-800',
        'scheduled' => 'bg-info-200 text-info-800',
        'pending' => 'bg-warn-100 text-warn-800',
        'running' => 'bg-warn-200 text-warn-800',
        'active' => 'bg-ok-100 text-ok-800',
        'complete' => 'bg-ok-100 text-ok-800',
        'passed' => 'bg-ok-100 text-ok-800',
        'set' => 'bg-ok-100 text-ok-800',
        'approved' => 'bg-ok-200 text-ok-800',
        'paid' => 'bg-ok-600 text-bg',
        'failed' => 'bg-accent-100 text-accent-800',
        'missing' => 'bg-accent-100 text-accent-800',
        'overdue' => 'bg-accent text-bg',
        'cancelled' => 'bg-neutral-300 text-neutral-900',
        'closed' => 'bg-neutral-300 text-neutral-900',
        'disabled' => 'bg-neutral-300 text-neutral-900',
        'removed' => 'bg-neutral-300 text-neutral-900',
        'expired' => 'bg-neutral-300 text-neutral-900',
    ];

    $key = (string) $state;
    $registered = app(\App\Core\Packages\Hooks::class)->statuses()[$key] ?? null;
    $resolved = $registered['like'] ?? $key;

    if (! isset($map[$resolved])) {
        logger()->warning('Unknown status chip state.', ['state' => $key]);
        $resolved = 'draft';
    }

    $label = $label ?? ($registered['label'] ?? str_replace('_', ' ', $key));
@endphp

<span {{ $attributes->class(['inline-flex px-2 py-1 text-micro uppercase', $map[$resolved]]) }}>{{ $label }}</span>
