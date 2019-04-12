<?php
declare(strict_types=1);

namespace Toll\Domain\ExternalSystem;

use Toll\Domain\Value;

interface Notifications
{
    public function sendBillForTicket(Value\BillingInformation $billingInformation, Value\PrePaidTicketId $ticketId) : void;
}
