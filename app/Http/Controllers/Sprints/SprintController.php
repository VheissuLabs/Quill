<?php

namespace App\Http\Controllers\Sprints;

use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\SprintRequest;
use App\Http\Resources\IssueResource;
use App\Http\Resources\SprintResource;
use App\Models\BoardColumn;
use App\Models\Organization;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        $owner = auth()->user()->boardOwnerFor($currentOrganization);

        $sprints = $owner->sprints()
            ->with('owner')
            ->withCount([
                'issues',
                'issues as done_issues_count' => fn ($query) => $query->where('status', IssueStatus::Done),
            ])
            ->withSum('issues', 'points')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('sprints/Index', [
            'sprints' => SprintResource::collection($sprints),
        ]);
    }

    public function store(SprintRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $sprint = $request->user()->boardOwnerFor($currentOrganization)->sprints()->create([
            ...$request->validated(),
            'status' => SprintStatus::Planned,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint created.')]);

        return to_route('sprints.show', ['sprint' => $sprint]);
    }

    public function show(Organization $currentOrganization, Sprint $sprint): Response
    {
        $sprint->load('owner.columns')->loadCount([
            'issues',
            'issues as done_issues_count' => fn ($query) => $query->where('status', IssueStatus::Done),
        ])->loadSum('issues', 'points');

        $issues = $sprint->issues()
            ->with(['project', 'assignee', 'team', 'boardColumn'])
            ->orderBy('position')
            ->orderBy('number')
            ->get();

        return Inertia::render('sprints/Show', [
            'sprint' => SprintResource::make($sprint),
            'issues' => IssueResource::collection($issues),
            'columns' => $sprint->columns()->map(fn (BoardColumn $column) => [
                'id' => $column->id,
                'name' => $column->name,
                'status' => $column->status->value,
            ]),
        ]);
    }

    public function update(SprintRequest $request, Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        $sprint->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint updated.')]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        $ownPage = route('sprints.show', ['current_organization' => $currentOrganization, 'sprint' => $sprint]);

        $sprint->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint deleted.')]);

        if (url()->previous() === $ownPage) {
            return to_route('sprints.index');
        }

        return back(fallback: route('sprints.index'));
    }
}
