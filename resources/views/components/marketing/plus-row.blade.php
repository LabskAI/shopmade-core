<div {{ $attributes->class('group/row relative isolate pt-[calc(--spacing(2)+1px)] last:pb-[calc(--spacing(2)+1px)]') }}>
    <div aria-hidden="true" class="absolute inset-y-0 left-1/2 -z-10 w-screen -translate-x-1/2">
        <div class="absolute inset-x-0 top-0 border-t border-black/5"></div>
        <div class="absolute inset-x-0 top-2 border-t border-black/5"></div>
        <div class="absolute inset-x-0 bottom-0 hidden border-b border-black/5 group-last/row:block"></div>
        <div class="absolute inset-x-0 bottom-2 hidden border-b border-black/5 group-last/row:block"></div>
    </div>
    {{ $slot }}
</div>
