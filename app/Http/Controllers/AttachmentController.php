<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Download the attachment file securely with authorization enforcement.
     */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        if (! Storage::disk('local')->exists($attachment->path)) {
            abort(404, 'Attachment file not found on disk.');
        }

        return Storage::disk('local')->download(
            $attachment->path,
            $attachment->original_name,
            [
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
