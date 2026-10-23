{{--
  x-wordmark — Shopmade SHOP/MADE mark when APP_NAME is Shopmade; otherwise org/app name text.
  Props: size = 'sm' | 'md' | 'lg'. inverse = false | true.
--}}
@props(['size' => 'sm', 'inverse' => false])
@php
  $text = match ($size) { 'lg' => 'text-[56px]', 'md' => 'text-[28px]', default => 'text-[15px]' };
  $rule = match ($size) { 'lg' => 'h-1 my-1.5', 'md' => 'h-0.5 my-1', default => 'h-0.5 my-0.5' };
  $ink  = $inverse ? 'text-bg' : 'text-ink';
  $bar  = $inverse ? 'bg-bg' : 'bg-ink';
  $brand = (string) config('app.name');
  $isShopmade = strcasecmp($brand, 'Shopmade') === 0;
  $label = $isShopmade ? 'Shopmade' : (string) (config('app.org_name') ?: $brand);
@endphp
@if ($isShopmade)
<span {{ $attributes->merge(['class' => "inline-flex flex-col font-wordmark font-extrabold uppercase leading-[0.92] tracking-[-0.03em] {$text} {$ink}"]) }} aria-label="Shopmade">
  <span>Shop</span>
  <span class="{{ $rule }} {{ $bar }}" aria-hidden="true"></span>
  <span>Made</span>
</span>
@else
<span {{ $attributes->merge(['class' => "inline-flex font-wordmark font-extrabold uppercase leading-[0.92] tracking-[-0.03em] {$text} {$ink}"]) }} aria-label="{{ $label }}">{{ $label }}</span>
@endif
