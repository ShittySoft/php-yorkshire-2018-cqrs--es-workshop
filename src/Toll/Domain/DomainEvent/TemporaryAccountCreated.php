<?php
declare(strict_types=1);

namespace Toll\Domain\DomainEvent;

use Prooph\EventSourcing\AggregateChanged;
use Toll\Domain\Value\AccountId;

final class TemporaryAccountCreated extends AggregateChanged
{
    public function accountId() : AccountId
    {
    }
}
