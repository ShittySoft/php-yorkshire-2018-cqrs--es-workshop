<?php
declare(strict_types=1);

namespace Toll\Domain\ExternalSystem;

use Toll\Domain\Value;

interface PaymentGateway
{
    public function directlyCharge(Value\PaymentAmount $amount, Value\PaymentMethod $method) : Value\PaymentCharge;
    public function directlySendInvoice(Value\PaymentAmount $amount, Value\BillingInformation $billingInformation) : Value\InvoiceNumber;
}
