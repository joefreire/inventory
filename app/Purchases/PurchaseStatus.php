<?php

namespace App\Purchases;

/**
 * Order status. Moves forward only (awaiting -> confirmed -> shipped ->
 * delivered); cancelled and refunded are final and never change.
 */
enum PurchaseStatus: string
{
    case Awaiting = 'awaiting';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isFinal(): bool
    {
        return $this === self::Cancelled || $this === self::Refunded;
    }

    /**
     * Status after receiving $next: only advances in order, or to a final one.
     */
    public function advanceTo(self $next): self
    {
        if ($this->isFinal()) {
            return $this;
        }

        return $next->isFinal() || $next->rank() > $this->rank() ? $next : $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Awaiting => 'Aguardando confirmação',
            self::Confirmed => 'Confirmado',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
            self::Refunded => 'Reembolsado',
        };
    }

    private function rank(): int
    {
        return match ($this) {
            self::Awaiting => 0,
            self::Confirmed => 1,
            self::Shipped => 2,
            self::Delivered => 3,
            self::Cancelled, self::Refunded => 4,
        };
    }
}
