<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed users (admins, agents with category specializations, and customers).
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // 1. System Administrator
        User::firstOrCreate(
            ['email' => 'admin@supportdesk.test'],
            [
                'name' => 'System Administrator',
                'password' => $defaultPassword,
                'role' => UserRole::Admin,
                'is_available' => true,
            ]
        );

        // 2. Support Agents
        $agent1 = User::firstOrCreate(
            ['email' => 'agent1@supportdesk.test'],
            [
                'name' => 'Alex Rivera (Tier 2 Support)',
                'password' => $defaultPassword,
                'role' => UserRole::Agent,
                'is_available' => true,
                'max_open_tickets' => 15,
            ]
        );

        $agent2 = User::firstOrCreate(
            ['email' => 'agent2@supportdesk.test'],
            [
                'name' => 'Elena Rostova (Operations Specialist)',
                'password' => $defaultPassword,
                'role' => UserRole::Agent,
                'is_available' => true,
                'max_open_tickets' => 15,
            ]
        );

        $agent3 = User::firstOrCreate(
            ['email' => 'agent3@supportdesk.test'],
            [
                'name' => 'Marcus Chen (Security & Infra)',
                'password' => $defaultPassword,
                'role' => UserRole::Agent,
                'is_available' => true,
                'max_open_tickets' => 12,
            ]
        );

        $agent4 = User::firstOrCreate(
            ['email' => 'agent4@supportdesk.test'],
            [
                'name' => 'Sophia Taylor (Billing & Accounts)',
                'password' => $defaultPassword,
                'role' => UserRole::Agent,
                'is_available' => true,
                'max_open_tickets' => 20,
            ]
        );

        // Map Category specializations to Agents
        $techCategory = Category::where('slug', 'technical-support')->first();
        $billingCategory = Category::where('slug', 'billing-accounts')->first();
        $infraCategory = Category::where('slug', 'network-infrastructure')->first();
        $securityCategory = Category::where('slug', 'security-compliance')->first();
        $generalCategory = Category::where('slug', 'feature-requests-feedback')->first();

        if ($techCategory && $infraCategory) {
            $agent1->categories()->syncWithoutDetaching([$techCategory->id, $infraCategory->id]);
        }
        if ($techCategory && $generalCategory) {
            $agent2->categories()->syncWithoutDetaching([$techCategory->id, $generalCategory->id]);
        }
        if ($infraCategory && $securityCategory) {
            $agent3->categories()->syncWithoutDetaching([$infraCategory->id, $securityCategory->id]);
        }
        if ($billingCategory && $generalCategory) {
            $agent4->categories()->syncWithoutDetaching([$billingCategory->id, $generalCategory->id]);
        }

        // 3. Demo Corporate Customers
        $customers = [
            ['email' => 'customer1@supportdesk.test', 'name' => 'Acme Corporation (Dev Team)'],
            ['email' => 'customer2@supportdesk.test', 'name' => 'Globex Industries (IT Dept)'],
            ['email' => 'sarah@cyberdyne.test', 'name' => 'Sarah Connor (Cyberdyne Systems)'],
            ['email' => 'bruce@waynecorp.test', 'name' => 'Bruce Wayne (Wayne Enterprises)'],
            ['email' => 'tony@starkindustries.test', 'name' => 'Tony Stark (Stark Industries)'],
            ['email' => 'ripley@weyland-yutani.test', 'name' => 'Ellen Ripley (Weyland-Yutani)'],
        ];

        foreach ($customers as $c) {
            User::firstOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'password' => $defaultPassword,
                    'role' => UserRole::Customer,
                    'is_available' => true,
                ]
            );
        }
    }
}
