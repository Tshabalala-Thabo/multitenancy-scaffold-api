<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Tenant extends BaseTenant
{
    use HasFactory;

    protected $guarded = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo_path',
        'description',
        'privacy_setting',
        'two_factor_auth_required',
        'password_policy',
        'features',
    ];

    protected $casts = [
        'two_factor_auth_required' => 'boolean',
        'password_policy' => 'array',
        'features' => 'array',
    ];

    protected $attributes = [
        'privacy_setting' => 'private',
        'two_factor_auth_required' => false,
        'password_policy' => '{
            "min_length": 8,
            "requires_uppercase": true,
            "requires_lowercase": true,
            "requires_number": true,
            "requires_symbol": true
        }',
        'features' => '{
            "announcements_enabled": true,
            "analytics_enabled": false
        }'
    ];

    /**
     * @return void
     */
    public function deleteLogo(): void
    {
        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            Storage::disk('public')->delete($this->logo_path);
        }
    }


    /**
     * Get the address associated with the tenant.
     */
    public function address()
    {
        return $this->hasOne(Address::class);
    }

    /**
     * @param $file
     * @return mixed
     */
    public function storeLogo($file): mixed
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }

        $path = $file->store('tenant-logos', 'public');
        $this->update(['logo_path' => $path]);

        return $path;
    }

    /**
     * Get the logo URL.
     *
     * @return string|null
     */
    public function getLogoUrl()
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @return HasMany
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Make the tenant current.
     *
     * @return $this
     */
    public function makeCurrent(): static
    {
        return tap($this, function () {
            static::current($this);
        });
    }
}
