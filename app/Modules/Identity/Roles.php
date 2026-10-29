<?php

namespace App\Modules\Identity;

/**
 * Rolurile personalului (panoul /panou). Partenerii B2B și clienții nu sunt utilizatori de panou: au conturi
 * de client, iar rolurile lor în organizație sunt în App\Modules\B2B\OrganizationRole.
 *
 * Administratorul tehnic gestionează conturi, integrări și actualizări; nu primește implicit acces la rețete.
 */
final class Roles
{
    public const ADMIN_TEHNIC = 'administrator';

    public const MANAGER = 'manager';

    public const VANZARI = 'vanzari';

    public const SPECIALIST_OPTIC = 'specialist_optic';

    public const PRODUCTIE = 'productie';

    public const EDITOR_SITE = 'editor_continut';

    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            self::ADMIN_TEHNIC => 'Administrator',
            self::MANAGER => 'Manager',
            self::VANZARI => 'Vânzări',
            self::SPECIALIST_OPTIC => 'Specialist optic',
            self::PRODUCTIE => 'Producție',
            self::EDITOR_SITE => 'Editor conținut',
        ];
    }

    public static function label(string $role): string
    {
        return self::all()[$role] ?? $role;
    }

    /** Rolurile care pot consulta rețetele (în limita permisiunilor acordate). */
    public static function prescriptionReaders(): array
    {
        return [self::SPECIALIST_OPTIC, self::PRODUCTIE, self::MANAGER];
    }
}
