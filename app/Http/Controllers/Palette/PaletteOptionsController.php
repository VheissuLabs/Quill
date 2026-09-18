<?php

namespace App\Http\Controllers\Palette;

use App\Enums\OrganizationCapability;
use App\Enums\ProjectVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Palette\PaletteOptionsRequest;
use App\Http\Resources\IssueFormResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class PaletteOptionsController extends Controller
{
    public function index(PaletteOptionsRequest $request, Organization $currentOrganization): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'issue' => $currentOrganization->canUse(OrganizationCapability::Issues)
                ? IssueFormResource::make($currentOrganization, $user, $user->currentTeamFor($currentOrganization))->resolve()
                : null,
            'visibilities' => ProjectVisibility::options(),
        ]);
    }
}
