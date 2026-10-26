<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Local demo data. Every account's password is "password"; never run this on a real install.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $this->seedUser('owner@shopmade.test', 'Ava Chen', UserRole::SuperAdmin, $password);
        $this->seedUser('admin@shopmade.test', 'Jordan Reeves', UserRole::Admin, $password);
        $this->seedUser('staff@shopmade.test', 'Maria Lopez', UserRole::User, $password);
        $client = $this->seedUser('client@shopmade.test', 'Helen Park', UserRole::Client, $password);

        Setting::put('org.name', 'Shopmade');
        Setting::put('org.logo', '/images/logo.svg');

        Artisan::call('plugin:sync');

        if (Customer::query()->exists()) {
            $this->command?->warn('Customers already exist; skipping sample customers. Users and settings were refreshed.');

            return;
        }

        $customers = $this->seedDemoCustomers();
        $customers->push($this->seedManagedSiteCustomer());
        $client->customers()->syncWithoutDetaching($customers->take(2)->pluck('id')->all());
    }

    private function seedUser(string $email, string $name, UserRole $role, string $password): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'role' => $role],
        );
    }

    /**
     * @return Collection<int, Customer>
     */
    private function seedDemoCustomers(): Collection
    {
        $rows = [
            ['Helen Park', '(415) 555-0101', 'helen.park@example.com', '18 Cedar Ave, Oakland, CA', 'Annual HVAC tune-up, prefers morning slots'],
            ['David Nguyen', '(415) 555-0102', 'david.nguyen@example.com', '442 24th St, San Francisco, CA', 'Small plumbing jobs, text before arrival'],
            ['Priya Shah', '(510) 555-0103', 'priya.shah@example.com', '9 Marina Blvd, Alameda, CA', 'Property manager — gate code 4412'],
            ['Tom Alvarez', '(510) 555-0104', 'tom.alvarez@example.com', '77 Telegraph, Berkeley, CA', 'Landscape refresh after patio install'],
            ['Sofia Rossi', '(925) 555-0105', 'sofia.rossi@example.com', '1200 Oak Hill, Walnut Creek, CA', 'Recurring gutter cleaning'],
            ['Kenji Mori', '(415) 555-0106', 'kenji.mori@example.com', '5 Castro Ct, San Francisco, CA', 'Electrical panel upgrade quoted'],
        ];

        return collect($rows)->map(fn (array $row) => Customer::query()->create([
            'name' => $row[0],
            'phone' => $row[1],
            'email' => $row[2],
            'address' => $row[3],
            'notes' => $row[4],
        ]));
    }

    private function seedManagedSiteCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Harbor Point Plaza',
            'phone' => '(555) 555-0195',
            'email' => 'manager@example.com',
            'address' => '100 Example Ave, Riverton, ST 00000',
            'billing_name' => 'Example Property Management, LLC',
            'primary_contact_name' => 'Dana Example',
            'primary_contact_email' => 'manager@example.com',
            'visit_rate' => 325,
            'payment_terms' => 'Net 15',
            'notes' => 'Property-managed site. Use structured account fields for bill-to and terms.',
        ]);
    }
}
