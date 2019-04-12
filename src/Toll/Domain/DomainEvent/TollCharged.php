<?php
declare(strict_types=1);

namespace Toll\Domain\DomainEvent;

use Prooph\EventSourcing\AggregateChanged;
use Toll\Domain\Value\AccountId;
use Toll\Domain\Value\TollId;

class TollCharged extends AggregateChanged
{
    public static function forAccount(AccountId $account, TollId $toll) : self
    {
    }

    public function tollId() : TollId
    {
    }

    public function accountId() : AccountId
    {
    }
}
