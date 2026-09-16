<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateBoardColumnOrderRequest;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class BoardColumnOrderController extends Controller
{
    public function update(UpdateBoardColumnOrderRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        DB::transaction(function () use ($request, $team) {
            foreach (array_values($request->validated('column_ids')) as $position => $columnId) {
                $team->columns()->whereKey($columnId)->update(['position' => $position]);
            }
        });

        return back();
    }
}
