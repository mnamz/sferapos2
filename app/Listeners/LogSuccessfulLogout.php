<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event)
    {
        // Log audit for user login
        $event->user->auditEvent('Logged out');
    }
}
