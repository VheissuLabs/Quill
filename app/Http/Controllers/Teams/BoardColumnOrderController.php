<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateBoardColumnOrderRequest;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class BoardColumnOrderController extends Controller
{
    public function update(UpdateBoardColumnOrderRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        $team->reorderColumns($request->validated('column_ids'));

        return back();
    }
}
