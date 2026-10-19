<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\ChangeStatusRequest;
use App\Http\Requests\StoreReplyRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_ticket_request_validates_required_fields(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        // 1. Missing subject and description
        $validator = Validator::make([
            'priority' => 'high',
        ], (new StoreTicketRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('subject', $validator->errors()->toArray());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());

        // 2. Invalid priority
        $validator = Validator::make([
            'subject' => 'Issue with login',
            'description' => 'Detailed explanation here',
            'priority' => 'ultra-high', // invalid
        ], (new StoreTicketRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('priority', $validator->errors()->toArray());

        // 3. Inactive category
        $inactiveCat = Category::factory()->create(['is_active' => false]);
        $validator = Validator::make([
            'subject' => 'Issue with login',
            'description' => 'Detailed explanation here',
            'priority' => TicketPriority::High->value,
            'category_id' => $inactiveCat->id,
        ], (new StoreTicketRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category_id', $validator->errors()->toArray());

        // 4. Valid payload
        $validator = Validator::make([
            'subject' => 'Issue with login',
            'description' => 'Detailed explanation here',
            'priority' => TicketPriority::High->value,
            'category_id' => $category->id,
        ], (new StoreTicketRequest)->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_change_status_request_validates_status_enum(): void
    {
        $rules = (new ChangeStatusRequest)->rules();

        $invalid = Validator::make(['status' => 'non_existent_status'], $rules);
        $this->assertTrue($invalid->fails());

        $valid = Validator::make(['status' => TicketStatus::Resolved->value], $rules);
        $this->assertFalse($valid->fails());
    }

    public function test_store_reply_request_prohibits_internal_notes_for_customers(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        // Customer request rules
        $customerRequest = new StoreReplyRequest;
        $customerRequest->setUserResolver(fn () => $customer);

        $customerValidator = Validator::make([
            'body' => 'Customer reply message',
            'is_internal' => true,
        ], $customerRequest->rules());

        // Must fail because is_internal is prohibited for customer
        $this->assertTrue($customerValidator->fails());
        $this->assertArrayHasKey('is_internal', $customerValidator->errors()->toArray());

        // Agent request rules
        $agentRequest = new StoreReplyRequest;
        $agentRequest->setUserResolver(fn () => $agent);

        $agentValidator = Validator::make([
            'body' => 'Private staff diagnostic note',
            'is_internal' => true,
        ], $agentRequest->rules());

        // Must pass for staff
        $this->assertFalse($agentValidator->fails());
    }
}
