<?php

declare(strict_types=1);

namespace Toll\Domain\Aggregate;

use Assert\Assert;
use Prooph\EventSourcing\AggregateRoot;
use Toll\Domain\Value;
use Toll\Domain\ReadModel;
use Toll\Domain\ExternalSystem;
use Toll\Domain\DomainEvent;

final class Toll extends AggregateRoot
{
    /** @var Value\TollId */
    private $id;

    /** @var Value\AccountId */
    private $accountId;

    /** @var Value\PrePaidTicketId|null */
    private $usedTicketId;

    private $closed = false;

    public static function charge(Value\AccountId $account) : self
    {
        $instance = new self();

        $instance->recordThat(DomainEvent\TollCharged::forAccount(
            $account,
            Value\TollId::generate()
        ));

        return $instance;
    }

    public function useCredits(
        ReadModel\GetNextAccountPrepaidTicket $tickets,
        ReadModel\GetAccountDefaultPaymentMethod $accountPaymentMethods,
        ExternalSystem\PaymentGateway $paymentGateway,
        ReadModel\GetAccountBillingInformation $accountBillingInformation
    ) : void {
        Assert::that($this->closed)->false();

        $ticket = $tickets->__invoke($this->accountId);

        if ($ticket !== null) {
            $this->recordThat(DomainEvent\TicketInvalidated::forToll(
                $this->id,
                $ticket
            ));

            return;
        }

        $paymentMethod = $accountPaymentMethods->__invoke($this->accountId);

        if ($paymentMethod !== null) {
            $this->recordThat(DomainEvent\ExistingPaymentMethodCharged::withChargeResult(
                $this->id,
                $paymentGateway->directlyCharge(Value\PaymentAmount::defaultAmountForToll(), $paymentMethod)
            ));

            return;
        }

        $this->recordThat(DomainEvent\InvoiceSentToBillingAddress::withInvoiceId(
            $this->id,
            $paymentGateway->directlySendInvoice(
                Value\PaymentAmount::defaultAmountForToll(),
                $accountBillingInformation->__invoke($this->accountId)
            )
        ));
    }

    public function sendUsedTicketBill(
        ReadModel\GetAccountBillingInformation $billingInformation,
        ExternalSystem\Notifications $notifications
    ) : void {
        Assert::that($this->closed)->false();
        Assert::that($this->usedTicketId)->notNull();

        $notifications->sendBillForTicket($billingInformation->__invoke($this->accountId), $this->usedTicketId);

        $this->recordThat(DomainEvent\BillWasSent::for($this->id));
    }

    protected function whenTollCharged(DomainEvent\TollCharged $event) : void
    {
        $this->id        = $event->tollId();
        $this->accountId = $event->accountId();
    }

    protected function whenTicketInvalidated(DomainEvent\TicketInvalidated $event) : void
    {
        $this->usedTicketId = $event->ticketId();
    }

    protected function whenExistingPaymentMethodCharged(DomainEvent\ExistingPaymentMethodCharged $event) : void
    {
        $this->closed = true;
    }

    protected function whenInvoiceSentToBillingAddress(DomainEvent\InvoiceSentToBillingAddress $event) : void
    {
        $this->closed = true;
    }

    protected function whenBillWasSent(DomainEvent\BillWasSent $event) : void
    {
        $this->closed = true;
    }

    /**
     * @return string representation of the unique identifier of the aggregate root
     */
    protected function aggregateId()
    {
        return $this->id;
    }
}
