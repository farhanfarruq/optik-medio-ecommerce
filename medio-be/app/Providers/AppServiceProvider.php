<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Complain;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ServiceClaim;
use App\Observers\AppointmentObserver;
use App\Observers\ComplainObserver;
use App\Observers\OrderObserver;
use App\Observers\ReturnObserver;
use App\Observers\ServiceClaimObserver;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
    }

    public function boot(): void
    {
        // Register observers — notifikasi admin & customer otomatis
        Order::observe(OrderObserver::class);
        Complain::observe(ComplainObserver::class);
        ReturnRequest::observe(ReturnObserver::class);
        Appointment::observe(AppointmentObserver::class);
        ServiceClaim::observe(ServiceClaimObserver::class);

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Default password policy
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->numbers();
        });
    }
}
