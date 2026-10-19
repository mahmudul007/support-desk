<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        if (! $ticket instanceof Ticket) {
            return false;
        }

        return $this->user()?->can('view', $ticket) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isStaff = $user?->isAdmin() || $user?->isAgent();

        return [
            'body' => ['required', 'string'],
            'is_internal' => [$isStaff ? 'sometimes' : 'prohibited', 'boolean'],
            'set_waiting_for_customer' => [$isStaff ? 'sometimes' : 'prohibited', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,png,jpg,jpeg,gif,txt,csv,docx,xlsx'],
        ];
    }
}
