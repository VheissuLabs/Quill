<?php

namespace App\Mcp\Tools\Organizations;

use App\Enums\OrganizationModule;
use App\Http\Requests\Organizations\UpdateOrganizationModulesRequest;
use App\Http\Resources\OrganizationSettingsResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Switch an organization\'s product modules and their capabilities on or off. Pass only the flags to change; the rest keep their current value. Turning teams off dissolves every team. Owners only.')]
class UpdateOrganizationModulesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'manageModules', $organization);

            $current = collect($organization->moduleSettings())
                ->flatMap(fn (array $setting, string $module) => [
                    "{$module}_enabled" => $setting['enabled'],
                    ...collect($setting['capabilities'])->mapWithKeys(fn (bool $enabled, string $capability) => ["{$module}_{$capability}_enabled" => $enabled])->all(),
                ])
                ->all();

            $validated = $this->formRequest(UpdateOrganizationModulesRequest::class, $request, ['current_organization' => $organization], 'PATCH', [
                ...$current,
                ...collect($request->all())->only(array_keys($current))->all(),
            ]);

            $organization->updateModuleSettings($validated->validated());

            if (! $validated->boolean('teams_enabled')) {
                $organization->dissolveTeams();
            }

            return Response::json(OrganizationSettingsResource::make($organization->refresh(), $this->user($request))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        $flags = collect(OrganizationModule::cases())
            ->flatMap(fn (OrganizationModule $module) => [
                "{$module->value}_enabled" => $schema->boolean()->description("Turn the {$module->value} module on or off."),
                ...collect($module->capabilities())->mapWithKeys(fn ($capability) => [
                    "{$module->value}_{$capability->value}_enabled" => $schema->boolean()->description("Turn {$capability->value} within the {$module->value} module on or off."),
                ])->all(),
            ])
            ->all();

        return [
            'organization' => $this->organizationArgument($schema),
            ...$flags,
        ];
    }
}
