<?php

namespace App\Support\IdeHelper;

use App\Models\BoardColumn;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Sprint;
use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Barryvdh\LaravelIdeHelper\Contracts\ModelHookInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Properties ide-helper cannot read off the schema: the aliases query scopes attach to a row,
 * and the two polymorphic owners, which it can only type as a bare model.
 */
class ComputedModelProperties implements ModelHookInterface
{
    /** @var array<class-string<Model>, array<string, string>> */
    private const PROPERTIES = [
        Feature::class => [
            'downvotes_count' => 'int|null',
            'score' => 'int|null',
            'user_vote' => 'int|null',
        ],
        Project::class => [
            'open_features_count' => 'int|null',
            'organization' => '\App\Models\Organization',
        ],
        Sprint::class => [
            'done_issues_count' => 'int|null',
            'issues_sum_points' => 'int|float|string|null',
            'owner' => '\App\Models\Organization|\App\Models\Team',
        ],
        BoardColumn::class => [
            'owner' => '\App\Models\Organization|\App\Models\Team',
        ],
    ];

    public function run(ModelsCommand $command, Model $model): void
    {
        foreach (self::PROPERTIES[$model::class] ?? [] as $name => $type) {
            $command->setProperty($name, $type, true, false);
        }
    }
}
