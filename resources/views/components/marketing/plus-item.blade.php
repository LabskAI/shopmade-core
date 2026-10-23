<div {{ $attributes->class('group/item relative') }}>
    <x-marketing.plus-icon placement="top left" class="hidden group-first/item:block" />
    <x-marketing.plus-icon placement="top right" />
    <x-marketing.plus-icon placement="bottom left" class="hidden group-first/item:group-last/row:block" />
    <x-marketing.plus-icon placement="bottom right" class="hidden group-last/row:block" />
    {{ $slot }}
</div>
