<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\SmsGateway;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => ['user' => $request->user()],
            'permissions' => ['operate' => $request->user()?->can('operate') ?? false, 'administer' => $request->user()?->can('administer') ?? false],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
            'contactCount' => fn () => $request->user()?->can('operate') ? Contact::count() : 0,
            'smsMode' => app(SmsGateway::class)->dryRun() ? 'test' : (app(SmsGateway::class)->ready() ? 'configured' : 'unconfigured'),
            'smsProvider' => app(SmsGateway::class)->label(),
        ];
    }
}
