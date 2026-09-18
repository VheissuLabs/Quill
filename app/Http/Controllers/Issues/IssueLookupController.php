<?php

namespace App\Http\Controllers\Issues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\IssueLookupRequest;
use App\Http\Resources\IssueLookupResource;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IssueLookupController extends Controller
{
    public function index(IssueLookupRequest $request, Organization $currentOrganization): AnonymousResourceCollection
    {
        return IssueLookupResource::collection(
            Issue::matching($currentOrganization, $request->user(), $request->term(), $request->integer('except'))
        );
    }
}
