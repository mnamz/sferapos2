<?php

namespace App\Support;

/**
 * Process-local mute for the HQ observers.
 *
 * When this branch applies a transfer command that HQ sent, it changes its own
 * stock/serials — but HQ already records those moves via the transfer's own
 * ledger legs. Without a mute, the Product/ProductSerial observers would push
 * those changes BACK to HQ and double-count. The transfer applier wraps its
 * local writes in HqSyncMute::muted(fn () => ...) so the observers stay silent
 * for exactly that scope.
 */
class HqSyncMute
{
    private static bool $muted = false;

    public static function isMuted(): bool
    {
        return self::$muted;
    }

    /**
     * Run $callback with the HQ observers muted, restoring the prior state after
     * (nesting-safe).
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public static function muted(callable $callback)
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }
}
