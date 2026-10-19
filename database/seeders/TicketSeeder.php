<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Sla\SlaCalculator;
use App\Services\Tickets\TicketNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicketSeeder extends Seeder
{
    /**
     * Seed realistic support tickets with conversations, internal notes, and activity audit trails.
     */
    public function run(): void
    {
        $generator = app(TicketNumberGenerator::class);
        $slaCalculator = app(SlaCalculator::class);

        $agents = User::where('role', 'agent')->get()->keyBy('email');
        $customers = User::where('role', 'customer')->get()->keyBy('email');
        $admin = User::where('role', 'admin')->first();

        $categories = Category::all()->keyBy('slug');

        $agent1 = $agents['agent1@supportdesk.test'] ?? $agents->first();
        $agent2 = $agents['agent2@supportdesk.test'] ?? $agents->first();
        $agent3 = $agents['agent3@supportdesk.test'] ?? $agents->first();
        $agent4 = $agents['agent4@supportdesk.test'] ?? $agents->first();

        $custAcme = $customers['customer1@supportdesk.test'] ?? $customers->first();
        $custGlobex = $customers['customer2@supportdesk.test'] ?? $customers->first();
        $custCyber = $customers['sarah@cyberdyne.test'] ?? $customers->first();
        $custWayne = $customers['bruce@waynecorp.test'] ?? $customers->first();
        $custStark = $customers['tony@starkindustries.test'] ?? $customers->first();

        $techCat = $categories['technical-support'] ?? Category::first();
        $billingCat = $categories['billing-accounts'] ?? Category::first();
        $infraCat = $categories['network-infrastructure'] ?? Category::first();
        $secCat = $categories['security-compliance'] ?? Category::first();
        $feedbackCat = $categories['feature-requests-feedback'] ?? Category::first();

        $scenarios = [
            // 1. Critical Production Outage (In Progress, Agent 1)
            [
                'subject' => 'Production API Gateway returning HTTP 504 Gateway Timeout during peak traffic',
                'description' => "Our e-commerce checkout flow is failing because requests to /api/v1/orders are intermittently timing out. Nginx upstream logs show connections timing out after 30 seconds.\n\nImpacting approximately 25% of all active user checkouts.",
                'priority' => TicketPriority::Critical,
                'status' => TicketStatus::InProgress,
                'category' => $techCat,
                'customer' => $custAcme,
                'agent' => $agent1,
                'created_at' => now()->subMinutes(50),
                'assigned_at' => now()->subMinutes(40),
                'replies' => [
                    [
                        'user' => $agent1,
                        'is_internal' => false,
                        'body' => 'Hello Acme team, we are actively investigating the upstream timeout errors. We noticed sudden connection pool exhaustion on the cluster gateway.',
                        'created_at' => now()->subMinutes(35),
                    ],
                    [
                        'user' => $agent1,
                        'is_internal' => true,
                        'body' => 'Internal Note: Scaling out replica count from 4 to 8 instances on Kubernetes cluster us-east-1 to relieve immediate queue pressure.',
                        'created_at' => now()->subMinutes(30),
                    ],
                    [
                        'user' => $custAcme,
                        'is_internal' => false,
                        'body' => 'Thank you Alex! Please let us know once the additional replicas are healthy and taking traffic.',
                        'created_at' => now()->subMinutes(15),
                    ],
                ],
            ],

            // 2. High Priority Security & SSO Issue (Resolved, Agent 2)
            [
                'subject' => 'Unable to complete Okta SAML SSO handshake for newly provisioned engineers',
                'description' => "When new team members attempt to sign in via Okta SAML 2.0, they receive an error: 'Invalid audience restriction URI'. Existing users are still able to log in without issue.",
                'priority' => TicketPriority::High,
                'status' => TicketStatus::Resolved,
                'category' => $secCat,
                'customer' => $custCyber,
                'agent' => $agent2,
                'created_at' => now()->subHours(6),
                'assigned_at' => now()->subHours(5),
                'resolved_at' => now()->subHour(),
                'replies' => [
                    [
                        'user' => $agent2,
                        'is_internal' => false,
                        'body' => 'Hi Sarah, it appears the Entity ID configured in your Okta tenant was missing the trailing slash specified in your metadata certificate. We updated the tolerance settings on our identity provider.',
                        'created_at' => now()->subHours(3),
                    ],
                    [
                        'user' => $custCyber,
                        'is_internal' => false,
                        'body' => 'Confirmed! All 12 newly provisioned engineers were able to authenticate successfully. Thank you for the quick resolution.',
                        'created_at' => now()->subHours(2),
                    ],
                    [
                        'user' => $agent2,
                        'is_internal' => false,
                        'body' => 'Glad to hear! Marking this ticket as resolved. Feel free to reply if you encounter any other SSO questions.',
                        'created_at' => now()->subHour(),
                    ],
                ],
            ],

            // 3. Billing Inquiry (Waiting for Customer, Agent 4)
            [
                'subject' => 'Discrepancy in Enterprise subscription invoice #INV-2026-8819',
                'description' => 'Our finance department flagged an extra $450 charge for 3 unassigned seats on our latest invoice. We had requested seat downgrades prior to the billing cycle reset.',
                'priority' => TicketPriority::Medium,
                'status' => TicketStatus::WaitingForCustomer,
                'category' => $billingCat,
                'customer' => $custWayne,
                'agent' => $agent4,
                'created_at' => now()->subHours(4),
                'assigned_at' => now()->subHours(3),
                'replies' => [
                    [
                        'user' => $agent4,
                        'is_internal' => true,
                        'body' => 'Internal Note: Checked Stripe billing audit history. Downgrade webhook fired on the 1st of the month, 12 minutes after the recurring invoice was generated. Eligible for full credit note.',
                        'created_at' => now()->subHours(2),
                    ],
                    [
                        'user' => $agent4,
                        'is_internal' => false,
                        'body' => 'Hello Bruce, we investigated your subscription timeline and verified that the downgrade was submitted on the transition day. Would you prefer a \$450 direct refund to the payment card on file, or a credit applied to your next monthly invoice?',
                        'created_at' => now()->subHours(2),
                    ],
                ],
            ],

            // 4. Overdue Infrastructure Alert (Breached & Escalated, Agent 3)
            [
                'subject' => 'Database read replica replication lag exceeds 300 seconds threshold',
                'description' => 'Automated monitoring alert: read replica db-prod-ro-02 in region us-west-2 has fallen behind primary by 342 seconds. Read queries for reporting dashboards are serving stale data.',
                'priority' => TicketPriority::Critical,
                'status' => TicketStatus::Open,
                'category' => $infraCat,
                'customer' => $custStark,
                'agent' => $agent3,
                'created_at' => now()->subHours(4),
                'assigned_at' => now()->subHours(3),
                'breached_at' => now()->subHours(2),
                'escalated_at' => now()->subHour(),
                'escalation_count' => 1,
                'replies' => [
                    [
                        'user' => $agent3,
                        'is_internal' => true,
                        'body' => 'Internal Note: Replication thread is blocked by an unindexed full table scan initiated from analytics ETL batch. Terminating the blocking long-running query PID 48192.',
                        'created_at' => now()->subHours(2),
                    ],
                ],
            ],

            // 5. Open Ticket (Unassigned - Ready for Claiming)
            [
                'subject' => 'Request to whitelist CIDR block 198.51.100.0/24 on SFTP ingress firewall',
                'description' => 'Our automated nightly batch upload server needs access to your secure SFTP data pipeline. Please whitelist the source IP range 198.51.100.0/24 on port 22.',
                'priority' => TicketPriority::Medium,
                'status' => TicketStatus::Open,
                'category' => $secCat,
                'customer' => $custGlobex,
                'agent' => null,
                'created_at' => now()->subMinutes(25),
                'assigned_at' => null,
                'replies' => [],
            ],

            // 6. Open Ticket (Unassigned - Ready for Claiming)
            [
                'subject' => 'Webhook signatures failing verification after secret rotation',
                'description' => 'We rotated our endpoint signing secret in your developer settings dashboard today, but subsequent payloads still appear to be signed with the previous key hash.',
                'priority' => TicketPriority::High,
                'status' => TicketStatus::Open,
                'category' => $techCat,
                'customer' => $custAcme,
                'agent' => null,
                'created_at' => now()->subMinutes(15),
                'assigned_at' => null,
                'replies' => [],
            ],

            // 7. General Feature Request (Open - Low Priority)
            [
                'subject' => 'Add CSV and JSON export options for monthly customer support logs',
                'description' => 'We would like to request an automated feature to download full resolution transcripts and activity logs in CSV/JSON format for our quarterly compliance reviews.',
                'priority' => TicketPriority::Low,
                'status' => TicketStatus::Open,
                'category' => $feedbackCat,
                'customer' => $custWayne,
                'agent' => null,
                'created_at' => now()->subHours(12),
                'assigned_at' => null,
                'replies' => [],
            ],

            // 8. Historical Closed Ticket (Closed, Agent 1)
            [
                'subject' => 'SSL certificate expiration notice on staging vanity domain',
                'description' => "Let's Encrypt automated renewal failed for staging.acme-corp.test due to an ACME HTTP-01 challenge redirect mismatch.",
                'priority' => TicketPriority::High,
                'status' => TicketStatus::Closed,
                'category' => $infraCat,
                'customer' => $custAcme,
                'agent' => $agent1,
                'created_at' => now()->subDays(3),
                'assigned_at' => now()->subDays(3)->addMinutes(10),
                'resolved_at' => now()->subDays(2),
                'closed_at' => now()->subDay(),
                'replies' => [
                    [
                        'user' => $agent1,
                        'is_internal' => false,
                        'body' => 'Resolved Nginx ACME challenge path block. Issued updated certificate valid for 90 days.',
                        'created_at' => now()->subDays(2),
                    ],
                ],
            ],
        ];

        foreach ($scenarios as $s) {
            $ticketNumber = $generator->next();
            $timestamps = $slaCalculator->calculateTimestampsForPriority($s['priority'], $s['created_at']);

            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'subject' => $s['subject'],
                'description' => $s['description'],
                'priority' => $s['priority'],
                'status' => $s['status'],
                'category_id' => $s['category']->id,
                'created_by' => $s['customer']->id,
                'assigned_agent_id' => $s['agent']?->id,
                'assigned_at' => $s['assigned_at'] ?? null,
                'sla_due_at' => $s['breached_at'] ?? $timestamps['sla_due_at'],
                'sla_warning_at' => $timestamps['sla_warning_at'],
                'breached_at' => $s['breached_at'] ?? null,
                'escalated_at' => $s['escalated_at'] ?? null,
                'escalation_count' => $s['escalation_count'] ?? 0,
                'resolved_at' => $s['resolved_at'] ?? null,
                'closed_at' => $s['closed_at'] ?? null,
                'created_at' => $s['created_at'],
                'updated_at' => $s['created_at'],
            ]);

            // 1. Log ticket creation activity
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $s['customer']->id,
                'type' => ActivityType::Created,
                'new_value' => $ticket->ticket_number,
                'created_at' => $s['created_at'],
            ]);

            // 2. Log assignment activity if assigned
            if ($s['agent']) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $admin?->id ?? $s['agent']->id,
                    'type' => ActivityType::Assigned,
                    'new_value' => $s['agent']->name,
                    'created_at' => $s['assigned_at'] ?? $s['created_at'],
                ]);
            }

            // 3. Create replies and reply activities
            foreach ($s['replies'] as $replyData) {
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $replyData['user']->id,
                    'body' => $replyData['body'],
                    'is_internal' => $replyData['is_internal'],
                    'created_at' => $replyData['created_at'],
                    'updated_at' => $replyData['created_at'],
                ]);

                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $replyData['user']->id,
                    'type' => $replyData['is_internal'] ? ActivityType::InternalNote : ActivityType::Replied,
                    'new_value' => Str::limit($replyData['body'], 50),
                    'created_at' => $replyData['created_at'],
                ]);
            }

            // 4. Attach sample attachment metadata to the first critical ticket
            if ($s['priority'] === TicketPriority::Critical && ! empty($s['replies'])) {
                Attachment::create([
                    'ticket_id' => $ticket->id,
                    'ticket_reply_id' => null,
                    'uploaded_by' => $s['customer']->id,
                    'original_name' => 'nginx_upstream_timeout_error.log',
                    'path' => 'attachments/demo_sample_error.log',
                    'mime_type' => 'text/plain',
                    'size_bytes' => 14320,
                    'sha256' => hash('sha256', 'sample-log-contents'),
                    'created_at' => $s['created_at'],
                ]);
            }
        }
    }
}
