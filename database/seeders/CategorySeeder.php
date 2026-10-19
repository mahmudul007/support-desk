<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed default ticket categories.
     */
    public function run(): void
    {
        $categories = [
            'Technical Support' => 'Hardware, software, application defects, and error diagnostics.',
            'Billing & Accounts' => 'Invoicing, subscription renewals, payments, and refunds.',
            'Network & Infrastructure' => 'Connectivity, server downtime, DNS, and latency issues.',
            'Security & Compliance' => 'Access control, SSO/SAML, vulnerability reports, and certificates.',
            'Feature Requests & Feedback' => 'Product enhancement suggestions, pre-sales inquiries, and general feedback.',
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }
    }
}
