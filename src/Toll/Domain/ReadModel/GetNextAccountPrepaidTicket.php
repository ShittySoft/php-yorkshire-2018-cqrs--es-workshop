<?php
declare(strict_types=1);

namespace Toll\Domain\ReadModel;

use Toll\Domain\Value;

interface GetNextAccountPrepaidTicket
{
    public function __invoke(Value\AccountId $account) : ?Value\PrePaidTicketId;
}