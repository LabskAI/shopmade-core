<div
    x-data="{
        toasts: [],
        add(toast) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, tone: toast.tone || 'ok', message: toast.message });
            setTimeout(() => this.remove(id), 5000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }"
    x-on:toast.window="add($event.detail)"
    x-init="@if (session('status')) add({ tone: 'ok', message: {{ \Illuminate\Support\Js::from(session('status')) }} }) @endif"
    class="fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2 max-md:inset-x-4 max-md:w-auto"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="flex items-start justify-between gap-3 bg-surface px-4 py-3 shadow-md"
            :class="{
                'border-l-2 border-ok-600': toast.tone === 'ok',
                'border-l-2 border-warn-600': toast.tone === 'warn',
                'border-l-2 border-info-600': toast.tone === 'info',
                'border-l-2 border-accent': toast.tone === 'bad',
            }"
        >
            <p class="text-body text-ink" x-text="toast.message"></p>
            <button type="button" class="text-ink/55 hover:text-ink" @click="remove(toast.id)" aria-label="Dismiss">
                <i data-lucide="x" class="size-4"></i>
            </button>
        </div>
    </template>
</div>
