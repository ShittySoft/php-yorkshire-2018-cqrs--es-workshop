<?php
declare(strict_types=1);

namespace Toll\Domain\DomainEvent;

use Prooph\EventSourcing\AggregateChanged;
use Toll\Domain\Value\PaymentCharge;
use Toll\Domain\Value\TollId;

final class ExistingPaymentMethodCharged extends AggregateChanged
{
    public static function withChargeResult(TollId $id, PaymentCharge $directlyCharge) : self
    {
    }
}
