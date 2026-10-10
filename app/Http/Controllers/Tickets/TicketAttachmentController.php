<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\EmailAttachment;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function show(Organization $currentOrganization, EmailAttachment $attachment): StreamedResponse
    {
        return Storage::download($attachment->path, $attachment->filename, ['Content-Type' => $attachment->content_type]);
    }
}
