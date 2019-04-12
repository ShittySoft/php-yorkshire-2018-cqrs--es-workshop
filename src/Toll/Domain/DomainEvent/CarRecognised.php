<?php
declare(strict_types=1);

namespace Toll\Domain\DomainEvent;

use Prooph\EventSourcing\AggregateChanged;
use Toll\Domain\Value\AccountId;

final class CarRecognised extends AggregateChanged
{
    public function accountId() : AccountId
    {

    }
}
