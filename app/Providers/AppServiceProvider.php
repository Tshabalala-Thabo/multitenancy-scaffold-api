<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $url = config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
            
            // If we're in a tenant context, we might want to customize the URL
            if (tenant()) {
                // You could add tenant-specific customization here
                // For example, using the tenant's custom domain
                // $url = 'https://' . tenant('slug') . '.' . config('app.domain') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
            }
            
            return $url;
        });
        
        // Register tenant aware models if needed
        // For example, to make a model tenant-aware:
        // Model::resolveRelationUsing('tenant', function ($model) {
        //     return $model->belongsTo(Tenant::class);
        // });
    }
}
