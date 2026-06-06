<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Document;
use App\Policies\ConversationPolicy;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }
}
