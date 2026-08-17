<?php

namespace Database\Seeders;

use App\Models\IssueType;
use Illuminate\Database\Seeder;

class IssueTypeSeeder extends Seeder
{
    /**
     * The starting classifications. Organizations rename, reorder and retire
     * their own copies, so nothing in the application may branch on these names.
     *
     * @var array<int, string>
     */
    protected array $types = ['Bug', 'Feature', 'Enhancement'];

    public function run(): void
    {
        foreach ($this->types as $position => $name) {
            IssueType::firstOrCreate(
                ['organization_id' => null, 'name' => $name],
                ['position' => $position],
            );
        }
    }
}
