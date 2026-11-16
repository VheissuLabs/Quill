<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Observers\OrganizationScopedSlugObserver;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperClient */
#[UseFactory(ClientFactory::class)]
#[ObservedBy(OrganizationScopedSlugObserver::class)]
class Client extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<ClientDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(ClientDomain::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return BelongsTo<ClientContact, $this> */
    public function billingContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'billing_contact_id');
    }

    /** @return BelongsTo<Client, $this> */
    public function billedThrough(): BelongsTo
    {
        return $this->belongsTo(self::class, 'billed_through_client_id');
    }

    /** @return HasMany<Client, $this> */
    public function billedClients(): HasMany
    {
        return $this->hasMany(self::class, 'billed_through_client_id');
    }

    /** @return Collection<int, int> */
    public function billableClientIds(): Collection
    {
        return collect([$this->id])->merge($this->billedClients()->orderBy('id')->pluck('id'));
    }

    /** @return array{start: string, end: string}|null */
    public function currentBillingPeriod(): ?array
    {
        $period = $this->billing_cycle?->periodContaining(
            CarbonImmutable::now($this->organization->billingTimezone())->startOfDay(),
            $this->billing_cycle_starts_on ? CarbonImmutable::parse($this->billing_cycle_starts_on->toDateString()) : null,
        );

        return $period === null ? null : ['start' => $period[0]->toDateString(), 'end' => $period[1]->toDateString()];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('clients')
            ->logOnly(['name', 'billing_rate', 'billing_contact_id', 'billed_through_client_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'billing_rate' => 'decimal:2',
            'billing_cycle' => BillingCycle::class,
            'billing_cycle_starts_on' => 'date',
            'billing_cc_emails' => 'array',
            'payment_terms_days' => 'integer',
            'billing_opened_through' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
