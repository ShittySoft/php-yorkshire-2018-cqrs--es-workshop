<?php
declare(strict_types=1);

namespace Toll\Domain\ReadModel;

use Toll\Domain\Aggregate\Toll;
use Toll\Domain\DomainEvent;
use Toll\Domain\ReadModel;
use Toll\Domain\ExternalSystem;
use Toll\Domain\Repository;

final class WhenTicketInvalidatedThenSendBill
{
    /** @var Repository\Tolls */
    private $tolls;

    /** @var GetAccountBillingInformation */
    private $getBillingInformation;

    /** @var ExternalSystem\Notifications */
    private $notifications;

    public function __construct(
        Repository\Tolls $tolls,
        ReadModel\GetAccountBillingInformation $getBillingInformation,
        ExternalSystem\Notifications $notifications
    ) {
        $this->tolls                 = $tolls;
        $this->getBillingInformation = $getBillingInformation;
        $this->notifications         = $notifications;
    }

    public function __invoke(DomainEvent\TicketInvalidated $event) : void
    {
        $this->tolls
            ->get($event->tollId())
            ->sendUsedTicketBill($this->getBillingInformation, $this->notifications);
    }
}
