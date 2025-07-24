<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

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
     * Store a new logo for the tenant.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    public function storeLogo($file)
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
