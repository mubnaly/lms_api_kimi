<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\{HasDatabase, HasDomains};
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};

class Tenant extends BaseTenant implements TenantWithDatabase, HasMedia
{
    use HasDatabase, HasDomains, InteractsWithMedia;

    protected $fillable = [
        'id', 'name', 'organization_name', 'email', 'phone', 'address',
        'city', 'country', 'domain', 'primary_color', 'secondary_color',
        'is_active', 'subscription_ends_at', 'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'subscription_ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getLogoUrlAttribute()
    {
        return $this->getFirstMediaUrl('logo');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }
}
