<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Determine whether the user can download the attachment.
     */
    public function download(User $user, Attachment $attachment): bool
    {
        // 1. Must be authorized to view the parent ticket
        if (! $user->can('view', $attachment->ticket)) {
            return false;
        }

        // 2. If attachment is linked to an internal note, customers are strictly forbidden
        if ($attachment->isInternal() && $user->isCustomer()) {
            return false;
        }

        return true;
    }
}
