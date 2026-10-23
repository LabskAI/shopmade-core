<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

it('keeps app views on the Shopmade contract', function () {
    $files = collect(File::allFiles(resource_path('views')));

    if (is_dir(base_path('plugins'))) {
        $files = $files->merge(File::allFiles(base_path('plugins')));
    }

    $files = $files->filter(fn (SplFileInfo $file) => str_ends_with($file->getPathname(), '.blade.php'));

    $forbidden = [
        'rounded-',
        'bg-slate',
        'text-slate',
        'border-slate',
        'bg-red-',
        'text-red-',
        'border-red-',
        'ring-',
        'badgeClass',
    ];

    foreach ($files as $file) {
        $path = $file->getPathname();

        $guestCards = ['/auth/login.blade.php', '/auth/set-portal-password.blade.php', '/auth/portal-invite-invalid.blade.php'];

        if (str_contains($path, '/marketing/') || str_ends_with($path, '/welcome.blade.php') || collect($guestCards)->contains(fn (string $card): bool => str_ends_with($path, $card))) {
            continue;
        }

        $contents = File::get($path);

        foreach ($forbidden as $needle) {
            if (str_contains($contents, $needle)) {
                $this->fail("{$path} contains forbidden token {$needle}");
            }
        }
    }

    expect(true)->toBeTrue();
});

it('mounts exactly one toast host in the app layout', function () {
    $layout = File::get(resource_path('views/layouts/app.blade.php'));

    expect(substr_count($layout, '<x-toast-host'))->toBe(1);
});

it('hides the Features nav group when none are visible', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('uppercase text-ink/45">Features', false);
});
