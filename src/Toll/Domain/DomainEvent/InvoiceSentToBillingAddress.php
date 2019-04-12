<?php
declare(strict_types=1);

namespace Toll\Domain\DomainEvent;

use Prooph\EventSourcing\AggregateChanged;
use Toll\Domain\Value\InvoiceNumber;
use Toll\Domain\Value\TollId;

final class InvoiceSentToBillingAddress extends AggregateChanged
{
    public static function withInvoiceId(TollId $id, InvoiceNumber $directlySendInvoice) : self
    {
    }
}
