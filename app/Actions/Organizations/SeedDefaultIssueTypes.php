<?php

namespace App\Actions\Organizations;

use App\Models\IssueType;
use App\Models\Organization;
use Database\Seeders\IssueTypeSeeder;
use Illuminate\Support\Collection;

class SeedDefaultIssueTypes
{
    public function handle(Organization $organization): void
    {
        foreach ($this->templates() as $template) {
            IssueType::firstOrCreate([
                'organization_id' => $organization->id,
                'name' => $template->name,
            ], [
                'position' => $template->position,
            ]);
        }
    }

    /** @return Collection<int, IssueType> */
    protected function templates(): Collection
    {
        $templates = IssueType::whereNull('organization_id')->orderBy('position')->get();

        if ($templates->isEmpty()) {
            new IssueTypeSeeder()->run();

            $templates = IssueType::whereNull('organization_id')->orderBy('position')->get();
        }

        return $templates;
    }
}
