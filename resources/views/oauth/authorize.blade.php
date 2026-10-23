@extends('layouts.plain')

@section('title', 'Connect '.$clientName.' — '.config('app.name'))

@section('content')
    <style>
        .oauth-meta { font-size: 0.875rem; color: #57534e; }
        .oauth-notice { padding: 0.75rem 1rem; border: 2px solid #ec3013; background: #fff2ef; }
        .oauth-scopes { list-style: none; margin: 0 0 1.5rem; padding: 0; border-top: 2px solid rgba(32, 30, 29, 0.4); }
        .oauth-scopes li { padding: 0.75rem 0; border-bottom: 1px solid rgba(32, 30, 29, 0.16); line-height: 1.5; }
        .oauth-scopes label { display: flex; gap: 0.625rem; align-items: flex-start; cursor: pointer; }
        .oauth-scopes input { margin-top: 0.3rem; }
        .oauth-unavailable { color: #78716c; }
        .oauth-error { color: #ae1800; font-weight: 600; }
        .oauth-actions { display: flex; gap: 0.75rem; margin: 1.5rem 0; }
        .oauth-actions button { font: inherit; font-weight: 600; padding: 0.625rem 1.5rem; border: 2px solid #201e1d; background: transparent; color: #201e1d; cursor: pointer; }
        .oauth-actions .oauth-approve { border-color: #ec3013; background: #ec3013; color: #fff; }
        h2 { font-size: 1rem; margin: 1.5rem 0 0.5rem; }
    </style>

    <h1>Connect “{{ $clientName }}” to {{ config('app.name') }}?</h1>

    <p class="oauth-meta">
        “{{ $clientName }}” is the name the app gave itself; {{ config('app.name') }} has not verified it.
        After you choose, your browser goes back to <strong>{{ $redirectHost }}</strong>.
    </p>

    @if ($isLoopback)
        <p class="oauth-notice">
            The answer goes to a program on this computer (<strong>{{ $redirectHost }}</strong>), not to claude.ai.
            Continue only if you started this connection yourself, for example from the MCP Inspector or Claude Code.
        </p>
    @endif

    <p>Signed in as <strong>{{ $user->name }}</strong> ({{ $user->email }}).</p>

    <form method="POST" action="{{ route('oauth.authorize.store') }}">
        @csrf
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        @if ($granted !== [])
            <h2>If you approve, it can act as you to:</h2>
            <ul class="oauth-scopes">
                @foreach ($granted as $scope)
                    <li><strong>{{ $scope['label'] }}</strong> ({{ $scope['name'] }}) — {{ $scope['description'] }}</li>
                @endforeach
            </ul>
        @endif

        @if ($optional !== [])
            <h2>Only if you tick it:</h2>
            <ul class="oauth-scopes">
                @foreach ($optional as $scope)
                    <li>
                        <label>
                            <input type="checkbox" name="optional_scopes[]" value="{{ $scope['name'] }}">
                            <span>
                                <strong>{{ $scope['label'] }}</strong> ({{ $scope['name'] }}) — {{ $scope['description'] }}
                                Leave it unticked unless this connection needs it.
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($unavailable !== [])
            <h2>Requested, but not available to your account:</h2>
            <ul class="oauth-scopes oauth-unavailable">
                @foreach ($unavailable as $scope)
                    <li>
                        <label>
                            <input type="checkbox" disabled>
                            <span><strong>{{ $scope['label'] }}</strong> ({{ $scope['name'] }}) — your role ({{ $roleLabel }}) cannot grant it.</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($error)
            <p class="oauth-error" role="alert">{{ $error }}</p>
        @endif

        <div class="oauth-actions">
            <button type="submit" name="decision" value="approve" class="oauth-approve">Approve</button>
            <button type="submit" name="decision" value="deny">Deny</button>
        </div>
    </form>

    <p class="oauth-meta">An admin can revoke this connection at any time under Admin → Connectors.</p>
@endsection
