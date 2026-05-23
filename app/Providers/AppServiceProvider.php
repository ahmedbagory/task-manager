<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Policies\DepartmentPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaskCategoryPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Policies\WhatsappContactPolicy;
use App\Policies\WhatsappMessagePolicy;
use App\Support\Rbac;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
        Relation::enforceMorphMap([
            'user' => User::class,
            'department' => Department::class,
            // Legacy compatibility for historical invalid rows before cleanup runs.
            'all' => User::class,
        ]);

        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(TaskCategory::class, TaskCategoryPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(WhatsappContact::class, WhatsappContactPolicy::class);
        Gate::policy(WhatsappMessage::class, WhatsappMessagePolicy::class);

        Gate::before(static function (User $user): ?bool {
            return $user->hasRole(Rbac::SUPER_ADMIN) ? true : null;
        });
    }
}
