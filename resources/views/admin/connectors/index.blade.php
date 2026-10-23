@extends('layouts.app')

@section('title', 'Connectors')
@section('breadcrumb', 'Admin / Connectors')

@section('content')
    <x-page-header title="Connectors" kicker="Admin" />


    <p class="mb-6 max-w-3xl text-body text-ink/70">A connector lets a Claude session outside this server read and edit your website: pages, facts and theme. Edits are live at once and kept in each page's history. Every tool call is logged with the connector's name.</p>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 border-2 border-line bg-surface xl:col-span-2">
            <x-toolbar title="Connected clients" :count="$grants->count()" />
            <x-table :columns="[['label' => 'Client'], ['label' => 'User'], ['label' => 'Scopes'], ['label' => 'Created'], ['label' => 'Last used'], ['label' => '']]">
                @foreach ($grants as $grant)
                    <tr class="border-b border-line-soft">
                        <td class="px-4 py-3">
                            <div class="text-control">{{ $grant->client?->name ?? 'Unknown client' }}</div>
                            <div class="text-label text-ink/55" title="{{ $grant->client_id }}">{{ \Illuminate\Support\Str::limit($grant->client_id, 13, '…') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-control">{{ $grant->user?->name }}</div>
                            <div class="text-label text-ink/55">{{ $grant->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-control">{{ implode(', ', $grant->scopes ?? []) ?: '—' }}</td>
                        <td class="px-4 py-3 text-label tabular-nums whitespace-nowrap">{{ $grant->created_at?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-label tabular-nums whitespace-nowrap">{{ $grant->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3">
                            <x-dropdown>
                                <button type="button" @click="$dispatch('open-modal', 'connector-scopes-{{ $grant->id }}')">Change scopes</button>
                                <button type="button" class="danger" @click="$dispatch('open-modal', 'connector-revoke-{{ $grant->id }}')">Revoke</button>
                            </x-dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot:empty>
                    <x-empty title="No connectors yet" body="Follow the steps to connect a Claude session. It appears here after its first sign-in." />
                </x-slot:empty>
            </x-table>
        </div>

        <x-card title="Add connector instructions" kicker="claude.ai">
            <div class="grid gap-4">
                <p class="text-label text-ink/55">Requires a Pro, Max, Team or Enterprise plan.</p>

                <div
                    x-data="{
                        copied: false,
                        copy() {
                            const done = () => {
                                this.copied = true;
                                this.$dispatch('toast', { tone: 'ok', message: 'Connector URL copied.' });
                                setTimeout(() => this.copied = false, 2000);
                            };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(this.$refs.mcpUrl.value).then(done);
                                return;
                            }
                            this.$refs.mcpUrl.select();
                            document.execCommand('copy');
                            done();
                        },
                    }"
                    class="grid gap-2"
                >
                    <x-field label="Connector URL" for="mcp_url">
                        <x-input id="mcp_url" readonly value="{{ $mcpUrl }}" x-ref="mcpUrl" class="font-mono" />
                    </x-field>
                    <div>
                        <x-button variant="secondary" size="sm" @click="copy()">
                            <i data-lucide="copy" class="size-4"></i>
                            <span x-text="copied ? 'Copied' : 'Copy URL'">Copy URL</span>
                        </x-button>
                    </div>
                </div>

                <ol class="grid list-decimal gap-3 pl-5 text-body">
                    <li><strong>Customize → Connectors → Add custom connector</strong> (Team/Enterprise: an owner first adds it under <strong>Organization settings → Connectors</strong>, members then connect).</li>
                    <li>Name: <code>{{ $connectorName }}</code>. URL: <code class="break-all">{{ $mcpUrl }}</code>.</li>
                    <li>Authentication: <strong>Sign in now</strong>. OAuth client: <strong>Register automatically</strong> (Dynamic Client Registration). Leave request headers empty.</li>
                    <li>Click Add, then <strong>Connect</strong>: you are sent to your Shopmade login, then a consent screen listing the scopes (<code>director</code> to edit, <code>operator</code> to read). Approve.</li>
                    <li>In a conversation, <strong>+ → Connectors</strong> → enable it. The tools appear to that session.</li>
                </ol>

                <p class="text-label text-ink/55">Authentication settings cannot be changed after adding; remove and re-add to change them.</p>
            </div>
        </x-card>
    </div>

    @foreach ($grants as $grant)
        <x-modal name="connector-scopes-{{ $grant->id }}" title="Change scopes">
            <form id="connector-scopes-form-{{ $grant->id }}" method="POST" action="{{ route('admin.connectors.update', $grant) }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <p class="text-body text-ink/70">{{ $grant->client?->name ?? 'Claude' }} for {{ $grant->user?->name }}. Untick a scope to remove it. Only the scopes this user may hold are listed.</p>
                @forelse ($eligibleScopes[$grant->id] ?? [] as $scope)
                    <div class="grid gap-1">
                        @if ($grant->hasScope($scope))
                            <x-checkbox name="scopes[]" value="{{ $scope }}" :checked="true" :label="$scope" />
                            <p class="pl-7 text-label text-ink/55">{{ $scopeDescriptions[$scope] ?? '' }}</p>
                        @else
                            <p class="text-control text-ink/55">{{ $scope }}: not approved for this connector</p>
                            <p class="text-label text-ink/55">{{ $scopeDescriptions[$scope] ?? '' }} To add it, the user reconnects the connector and approves it on the consent screen.</p>
                        @endif
                    </div>
                @empty
                    <p class="text-body">This user may not hold any connector scope. Revoke the connector instead.</p>
                @endforelse
            </form>
            <x-slot:footer>
                <x-button variant="secondary" @click="$dispatch('close-modal', 'connector-scopes-{{ $grant->id }}')">Cancel</x-button>
                <x-button type="submit" form="connector-scopes-form-{{ $grant->id }}">Save scopes</x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="connector-revoke-{{ $grant->id }}" title="Revoke this connector?">
            {{ $grant->client?->name ?? 'Claude' }} for {{ $grant->user?->name }} loses access now: its tokens stop working. To use it again, connect it again from claude.ai.
            <x-slot:footer>
                <x-button variant="secondary" @click="$dispatch('close-modal', 'connector-revoke-{{ $grant->id }}')">Cancel</x-button>
                <form method="POST" action="{{ route('admin.connectors.destroy', $grant) }}">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit">Revoke connector</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    @endforeach
@endsection
