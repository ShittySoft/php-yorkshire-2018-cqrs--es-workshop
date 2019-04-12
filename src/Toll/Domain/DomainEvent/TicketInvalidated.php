<?php
declare(strict_types=1);

namespace Toll\Domain\DomainEvent;

use Prooph\EventSourcing\AggregateChanged;
use Toll\Domain\Value\PrePaidTicketId;
use Toll\Domain\Value\TollId;

class TicketInvalidated extends AggregateChanged
{
    public static function forToll(TollId $id, PrePaidTicketId $ticket) : self
    {
    }

    public function ticketId() : PrePaidTicketId
    {
    }

    public function tollId() : TollId
    {
    }
}
