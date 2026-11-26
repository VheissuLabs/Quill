<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentRequest;
use App\Models\Document;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class IssueDocumentController extends Controller
{
    public function store(DocumentRequest $request, Organization $currentOrganization, Issue $issue): RedirectResponse
    {
        Document::storeFor($issue, $currentOrganization->id, $request->document(), $request->user());

        return back();
    }
}
