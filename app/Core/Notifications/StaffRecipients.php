<?php

namespace App\Core\Notifications;

use App\Core\Settings\Settings;

/**
 * Adresele interne care primesc notificările (comenzi noi, plăți, anulări, cereri de pe site), din
 * Setări → Contact → „Emailuri interne pentru notificări” (mai multe adrese, separate prin virgulă).
 */
final class StaffRecipients
{
    /** @return list<string> */
    public static function emails(): array
    {
        return self::parse((string) app(Settings::class)->get('notifications.orders_email', ''));
    }

    /** @return list<string> */
    public static function parse(string $text): array
    {
        $list = preg_split('/[\s,;]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($list, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }
}
