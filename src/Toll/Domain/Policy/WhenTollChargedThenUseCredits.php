<?php
declare(strict_types=1);

namespace Toll\Domain\ReadModel;

use Toll\Domain\Aggregate\Toll;
use Toll\Domain\DomainEvent;
use Toll\Domain\ReadModel;
use Toll\Domain\ExternalSystem;
use Toll\Domain\Repository;

final class WhenTollChargedThenUseCredits
{
    /** @var Repository\Tolls */
    private $tolls;

    /** @var GetNextAccountPrepaidTicket */
    private $getTicket;

    /** @var GetAccountDefaultPaymentMethod */
    private $getPaymentMethod;

    /** @var GetAccountBillingInformation */
    private $getBillingInformation;

    /** @var ExternalSystem\PaymentGateway */
    private $paymentGateway;

    public function __construct(
        Repository\Tolls $tolls,
        ReadModel\GetNextAccountPrepaidTicket $getTicket,
        ReadModel\GetAccountDefaultPaymentMethod $getPaymentMethod,
        ExternalSystem\PaymentGateway $paymentGateway,
        ReadModel\GetAccountBillingInformation $getBillingInformation
    ) {
        $this->tolls                 = $tolls;
        $this->getTicket             = $getTicket;
        $this->getPaymentMethod      = $getPaymentMethod;
        $this->paymentGateway        = $paymentGateway;
        $this->getBillingInformation = $getBillingInformation;
    }

    public function __invoke(DomainEvent\TollCharged $event) : void
    {
        $this->tolls
            ->get($event->tollId())
            ->useCredits(
                $this->getTicket,
                $this->getPaymentMethod,
                $this->paymentGateway,
                $this->getBillingInformation
            );
    }
}
