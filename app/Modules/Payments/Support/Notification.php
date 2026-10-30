<?php

namespace App\Modules\Payments\Support;

/**
 * O notificare de la procesator, deja interpretată de adaptor. `signatureValid` vine din verificarea semnăturii
 * pe corpul brut; o notificare nesemnată corect se înregistrează, dar nu schimbă nimic.
 */
final class Notification
{
    public function __construct(
        public readonly string $eventId,
        public readonly bool $signatureValid,
        public readonly ?string $providerReference,
        public readonly ?string $status, // platita | autorizata | esuata | anulata | rambursata | in_asteptare
        public readonly ?int $amount,
        public readonly ?string $type = null,
        public readonly array $safePayload = [],
    ) {}
}
