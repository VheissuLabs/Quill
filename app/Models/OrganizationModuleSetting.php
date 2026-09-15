<?php

namespace App\Models;

use Database\Factories\OrganizationModuleSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An organization-level module or capability override.
 *
 * Missing settings are enabled by default; rows are written when an owner changes a setting.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $module
 * @property string $capability
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable(['organization_id', 'module', 'capability', 'enabled'])]
class OrganizationModuleSetting extends Model
{
    /** @use HasFactory<OrganizationModuleSettingFactory> */
    use HasFactory;

    /**
     * Get the organization that owns the setting.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
