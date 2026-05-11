<?php

namespace App\Providers;

use App\Models\Complain;
use App\Observers\ComplainObserver;
use App\Models\ReturnRequest;
use App\Observers\ReturnObserver;
use App\Models\Order;
use App\Observers\OrderObserver;
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
        // Register observers
        Complain::observe(ComplainObserver::class);
        ReturnRequest::observe(ReturnObserver::class);
        Order::observe(OrderObserver::class);

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
