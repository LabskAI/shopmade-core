{{-- One service row of the facts form. Pass $index, $slug, $names (language => name) and $languages. --}}
<div class="grid gap-3 border-b border-line-soft pb-4 sm:grid-cols-2" data-service-row="{{ $index }}">
    <x-field label="Slug" for="service-{{ $index }}-slug" :error="$errors->first('services.'.$index.'.slug') ?: $errors->first('services.'.$index)">
        <x-input
            id="service-{{ $index }}-slug"
            name="services[{{ $index }}][slug]"
            maxlength="80"
            autocapitalize="none"
            autocomplete="off"
            placeholder="house-cleaning"
            :error="$errors->has('services.'.$index.'.slug') || $errors->has('services.'.$index)"
            value="{{ $slug }}"
        />
    </x-field>
    @foreach ($languages as $language)
        <x-field label="Name ({{ strtoupper($language) }})" for="service-{{ $index }}-name-{{ $language }}" :error="$errors->first('services.'.$index.'.name.'.$language)">
            <x-input
                id="service-{{ $index }}-name-{{ $language }}"
                name="services[{{ $index }}][name][{{ $language }}]"
                maxlength="200"
                :error="$errors->has('services.'.$index.'.name.'.$language)"
                value="{{ is_string($names[$language] ?? null) ? $names[$language] : '' }}"
            />
        </x-field>
    @endforeach
</div>
