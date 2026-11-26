<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function show(Organization $currentOrganization, Document $document): StreamedResponse
    {
        return Storage::response($document->path, $document->name, ['Content-Type' => $document->content_type]);
    }

    public function destroy(Organization $currentOrganization, Document $document): RedirectResponse
    {
        $document->delete();

        return back();
    }
}
