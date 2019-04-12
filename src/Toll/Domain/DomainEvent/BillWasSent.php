<?php
declare(strict_types=1);

namespace Toll\Domain\DomainEvent;
use Toll\Domain\Value;

use Prooph\EventSourcing\AggregateChanged;

final class BillWasSent extends AggregateChanged
{
    public static function for(Value\TollId $toll) : self
    {
    }
}
