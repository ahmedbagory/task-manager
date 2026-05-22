<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ApiSettings;
use App\Filament\Pages\WhatsAppSession;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\TaskCategories\TaskCategoryResource;
use App\Filament\Resources\TaskReports\TaskReportResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->userMenuItems([
                MenuItem::make()
                    ->label(__('API Settings'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => ApiSettings::getUrl())
                    ->visible(fn (): bool => filament()->auth()->user()?->can('settings.api.manage') ?? false),
                MenuItem::make()
                    ->label(__('WhatsApp Session'))
                    ->icon(Heroicon::OutlinedQrCode)
                    ->url(fn (): string => WhatsAppSession::getUrl())
                    ->visible(fn (): bool => filament()->auth()->user()?->can('settings.api.manage') ?? false),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->collapsedSidebarWidth('5rem')
            ->maxContentWidth(Width::Full)
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): HtmlString => new HtmlString(
                    view('components.locale-switcher', ['context' => 'login'])->render()
                )
            )
            ->resources([
                DepartmentResource::class,
                MobileNotificationResource::class,
                TaskCategoryResource::class,
                TaskResource::class,
                TaskReportResource::class,
                UserResource::class,
                RoleResource::class,
                WhatsappContactResource::class,
                WhatsappMessageResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
