<div class="grid gap-4 sm:grid-cols-2">
    <x-field class="sm:col-span-2" label="Name" for="name" :error="$errors->first('name')">
        <x-input id="name" name="name" value="{{ old('name', $customer->name ?? '') }}" required :error="$errors->has('name')" />
    </x-field>
    <x-field label="Phone" for="phone" :error="$errors->first('phone')">
        <x-input id="phone" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" :error="$errors->has('phone')" />
    </x-field>
    <x-field label="Email" for="email" :error="$errors->first('email')">
        <x-input id="email" name="email" type="email" value="{{ old('email', $customer->email ?? '') }}" :error="$errors->has('email')" />
    </x-field>
    <x-field class="sm:col-span-2" label="Address" for="address" :error="$errors->first('address')">
        <x-input id="address" name="address" value="{{ old('address', $customer->address ?? '') }}" :error="$errors->has('address')" />
    </x-field>
    <x-field class="sm:col-span-2" label="Managing / bill-to company" for="billing_name" :error="$errors->first('billing_name')" hint="Shown on the client portal as the company this site is billed to.">
        <x-input id="billing_name" name="billing_name" value="{{ old('billing_name', $customer->billing_name ?? '') }}" :error="$errors->has('billing_name')" />
    </x-field>
    <x-field label="Primary contact" for="primary_contact_name" :error="$errors->first('primary_contact_name')">
        <x-input id="primary_contact_name" name="primary_contact_name" value="{{ old('primary_contact_name', $customer->primary_contact_name ?? '') }}" :error="$errors->has('primary_contact_name')" />
    </x-field>
    <x-field label="Primary contact email" for="primary_contact_email" :error="$errors->first('primary_contact_email')">
        <x-input id="primary_contact_email" name="primary_contact_email" type="email" value="{{ old('primary_contact_email', $customer->primary_contact_email ?? '') }}" :error="$errors->has('primary_contact_email')" />
    </x-field>
    <x-field label="Visit rate" for="visit_rate" :error="$errors->first('visit_rate')" hint="Optional. Shown as dollars per visit.">
        <x-input id="visit_rate" name="visit_rate" type="number" min="0" step="0.01" value="{{ old('visit_rate', $customer->visit_rate ?? '') }}" :error="$errors->has('visit_rate')" />
    </x-field>
    <x-field label="Payment terms" for="payment_terms" :error="$errors->first('payment_terms')">
        <x-input id="payment_terms" name="payment_terms" value="{{ old('payment_terms', $customer->payment_terms ?? '') }}" placeholder="Net 15" :error="$errors->has('payment_terms')" />
    </x-field>
    <x-field class="sm:col-span-2" label="Notes" for="notes" :error="$errors->first('notes')">
        <x-textarea id="notes" name="notes" rows="4" :error="$errors->has('notes')">{{ old('notes', $customer->notes ?? '') }}</x-textarea>
    </x-field>
</div>
