{{-- Page fields shared by create and edit. Pass $values {title, description, nav_label, cities, body} and $cities (facts.cities). --}}
<x-field label="Title" for="title" :error="$errors->first('title')" hint="Shown in the browser tab, in search results and in AI answers.">
    <x-input id="title" name="title" maxlength="200" required :error="$errors->has('title')" value="{{ old('title', $values['title']) }}" />
</x-field>

<x-field label="Description" for="description" :error="$errors->first('description')" hint="One or two sentences for search results and AI assistants.">
    <x-textarea id="description" name="description" rows="2" maxlength="500" required :error="$errors->has('description')">{{ old('description', $values['description']) }}</x-textarea>
</x-field>

<div class="grid gap-4 sm:grid-cols-2">
    <x-field label="Menu label (optional)" for="nav_label" :error="$errors->first('nav_label')" hint="A shorter name for the navigation. Empty uses the title.">
        <x-input id="nav_label" name="nav_label" maxlength="80" :error="$errors->has('nav_label')" value="{{ old('nav_label', $values['nav_label']) }}" />
    </x-field>
    <x-field
        label="Cities this page serves (optional)"
        for="cities"
        :error="$errors->first('cities')"
        :hint="$cities === [] ? 'Comma-separated. Add your cities under Facts first.' : 'Comma-separated, from your facts: '.implode(', ', $cities).'.'"
    >
        <x-input id="cities" name="cities" maxlength="2000" :error="$errors->has('cities')" value="{{ old('cities', $values['cities']) }}" />
    </x-field>
</div>

<x-field label="Page text" for="body" :error="$errors->first('body')" hint="Markdown: # Heading, - list item, **bold**, [link text](services) to link another page. Raw HTML is removed.">
    <x-textarea id="body" name="body" rows="18" maxlength="100000" class="font-mono" :error="$errors->has('body')">{{ old('body', $values['body']) }}</x-textarea>
</x-field>
