<?php

namespace App\Providers;

use App\Modules\Admin\Console\GrantRoleCommand;
use App\Modules\Admin\Enums\Permission;
use App\Modules\Card\Models\Card;
use App\Modules\Identity\Models\PersonalAccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\Moderation\Models\Report;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Policies\CommentPolicy;
use App\Modules\Social\Policies\PostPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Generated URLs (signed media links, email links) always use the public API host,
        // not whichever internal hostname the web server used to reach the API.
        URL::forceRootUrl(config('app.url'));
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Stable aliases are stored instead of PHP class names (polymorphic columns, audit logs).
        Relation::enforceMorphMap([
            'user' => User::class,
            'post' => Post::class,
            'comment' => Comment::class,
            'card' => Card::class,
            'report' => Report::class,
        ]);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->max(128)->uncompromised()
            : Password::min(10)->max(128));

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);

        $this->configureRateLimiting();

        if ($this->app->runningInConsole()) {
            $this->commands([GrantRoleCommand::class]);
        }
    }

    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->getAuthIdentifier() ?: $request->ip();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($byUserOrIp($request)));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by('pwreset:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(30)->by('pwreset-ip:'.$request->ip()),
        ]);

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinutes(10, 6)->by('verify:'.$byUserOrIp($request)));

        RateLimiter::for('content', fn (Request $request) => Limit::perMinute(20)->by('content:'.$byUserOrIp($request)));

        RateLimiter::for('interactions', fn (Request $request) => Limit::perMinute(60)->by('interact:'.$byUserOrIp($request)));

        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(20)->by('reports:'.$byUserOrIp($request)));

        RateLimiter::for('card-link', fn (Request $request) => Limit::perHour(5)->by('card-link:'.$byUserOrIp($request)));

        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinutes(15, 10)->by('sensitive:'.$byUserOrIp($request)));
    }
}
