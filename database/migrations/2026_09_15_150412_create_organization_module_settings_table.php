<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_module_settings');

        Schema::create('organization_module_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->string('capability')->default('');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['organization_id', 'module', 'capability'],
                'org_module_settings_scope_unique',
            );
        });

        DB::table('organizations')->orderBy('id')->eachById(function (object $organization): void {
            $featureVotingEnabled = (bool) $organization->feature_voting_enabled;
            $issuesEnabled = (bool) $organization->issues_enabled;
            $sprintsEnabled = (bool) $organization->sprints_enabled;
            $now = now();

            DB::table('organization_module_settings')->insert([
                [
                    'organization_id' => $organization->id,
                    'module' => 'feature_voting',
                    'capability' => '',
                    'enabled' => $featureVotingEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'organization_id' => $organization->id,
                    'module' => 'feature_voting',
                    'capability' => 'boards',
                    'enabled' => $featureVotingEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'organization_id' => $organization->id,
                    'module' => 'feature_voting',
                    'capability' => 'voting',
                    'enabled' => $featureVotingEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'organization_id' => $organization->id,
                    'module' => 'development',
                    'capability' => '',
                    'enabled' => $issuesEnabled || $sprintsEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'organization_id' => $organization->id,
                    'module' => 'development',
                    'capability' => 'issues',
                    'enabled' => $issuesEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'organization_id' => $organization->id,
                    'module' => 'development',
                    'capability' => 'sprints',
                    'enabled' => $sprintsEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'organization_id' => $organization->id,
                    'module' => 'development',
                    'capability' => 'workflow',
                    'enabled' => $issuesEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'feature_voting_enabled',
                'issues_enabled',
                'sprints_enabled',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('feature_voting_enabled')->default(true);
            $table->boolean('issues_enabled')->default(true);
            $table->boolean('sprints_enabled')->default(true);
        });

        DB::table('organizations')->orderBy('id')->eachById(function (object $organization): void {
            $settings = DB::table('organization_module_settings')
                ->where('organization_id', $organization->id)
                ->whereIn('capability', ['', 'issues', 'sprints'])
                ->get()
                ->keyBy(fn (object $setting): string => $setting->module.':'.$setting->capability);

            $enabled = function (string $key) use ($settings): bool {
                $setting = $settings->get($key);

                return $setting === null || (bool) $setting->enabled;
            };

            DB::table('organizations')->where('id', $organization->id)->update([
                'feature_voting_enabled' => $enabled('feature_voting:'),
                'issues_enabled' => $enabled('development:issues'),
                'sprints_enabled' => $enabled('development:sprints'),
            ]);
        });

        Schema::dropIfExists('organization_module_settings');
    }
};
