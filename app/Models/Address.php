<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Address extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $fillable = [
        'street_address',
        'suburb',
        'city',
        'province',
        'postal_code',
    ];

    /**
     * Get the tenant that owns the address.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}