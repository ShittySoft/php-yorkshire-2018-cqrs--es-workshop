<?php
declare(strict_types=1);

namespace Toll\Domain\ReadModel;

use Toll\Domain\Value;

interface GetAccountBillingInformation
{
    public function __invoke(Value\AccountId $account) : Value\BillingInformation;
}
