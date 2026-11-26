<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentRequest;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectDocumentController extends Controller
{
    public function store(DocumentRequest $request, Organization $currentOrganization, Project $project): RedirectResponse
    {
        Document::storeFor($project, $currentOrganization->id, $request->document(), $request->user());

        return back();
    }
}
