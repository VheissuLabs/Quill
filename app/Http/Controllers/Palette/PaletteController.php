<?php

namespace App\Http\Controllers\Palette;

use App\Actions\Palette\SearchOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Palette\PaletteSearchRequest;
use App\Http\Resources\PaletteResultResource;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaletteController extends Controller
{
    public function index(PaletteSearchRequest $request, Organization $currentOrganization, SearchOrganization $search): AnonymousResourceCollection
    {
        return PaletteResultResource::collection(
            $search->handle($currentOrganization, $request->user(), $request->term())
        );
    }
}
