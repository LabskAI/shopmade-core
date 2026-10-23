@extends('layouts.app')

@section('title', 'Plugins')
@section('breadcrumb', 'Admin / Plugins')

@section('content')
    <x-page-header title="Plugins" kicker="Admin" />

    <div class="border-2 border-line bg-surface">
        <x-table :columns="[['label' => 'Plugin'], ['label' => 'Version'], ['label' => 'Source'], ['label' => 'Status'], ['label' => 'Actions']]">
            @foreach ($plugins as $plugin)
                <tr class="border-b border-line-soft">
                    <td class="px-4 py-3">
                        <div class="text-control">{{ $plugin->name }}</div>
                        <div class="text-label text-ink/55">{{ $plugin->slug }}</div>
                    </td>
                    <td class="px-4 py-3 text-label">{{ $plugin->version }}</td>
                    <td class="px-4 py-3 text-label">{{ $plugin->source->value }}</td>
                    <td class="px-4 py-3"><x-status :state="$plugin->status->value" /></td>
                    <td class="px-4 py-3">
                        <div class="flex justify-start gap-2">
                            @if ($plugin->status === \App\Enums\PluginStatus::Disabled)
                                <form method="POST" action="{{ route('admin.plugins.enable', $plugin) }}">
                                    @csrf
                                    <x-button variant="secondary" size="sm" type="submit">Enable</x-button>
                                </form>
                            @elseif ($plugin->status === \App\Enums\PluginStatus::Active)
                                <form method="POST" action="{{ route('admin.plugins.disable', $plugin) }}">
                                    @csrf
                                    <x-button variant="danger" size="sm" type="submit">Disable</x-button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-empty title="No plugins installed yet" body="Run plugin:sync to index the plugins folder." />
            </x-slot:empty>
        </x-table>
    </div>
@endsection
