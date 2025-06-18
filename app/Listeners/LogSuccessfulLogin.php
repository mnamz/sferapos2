<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event)
    {
        \Log::info('LogSuccessfulLogin listener triggered for user: ' . $event->user->id);
        $event->user->auditEvent('Logged in');
    }
}