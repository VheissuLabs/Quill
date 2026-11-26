<?php

namespace App\Http\Controllers\Palette;

use App\Http\Controllers\Controller;
use App\Http\Requests\Palette\PaletteOptionsRequest;
use App\Http\Resources\PaletteOptionsResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class PaletteOptionsController extends Controller
{
    public function index(PaletteOptionsRequest $request, Organization $currentOrganization): JsonResponse
    {
        return response()->json(PaletteOptionsResource::make($currentOrganization, $request->user())->resolve());
    }
}
