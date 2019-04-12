<?php
declare(strict_types=1);

namespace Toll\Domain\ReadModel;

use Toll\Domain\Aggregate\Toll;
use Toll\Domain\DomainEvent;
use Toll\Domain\Repository;

final class WhenCarRecognizedThenCollectToll
{
    /** @var Repository\Tolls */
    private $tolls;

    public function __construct(Repository\Tolls $tolls)
    {
        $this->tolls = $tolls;
    }

    public function __invoke(DomainEvent\CarRecognised $event) : void
    {
        $this->tolls->store(Toll::charge($event->accountId()));
    }
}
