<?php

declare(strict_types=1);

namespace Building\Infrastructure\ReadModel;

use Building\Domain\ReadModel\UserIsWhitelisted;

final class HardcodedUserIsWhitelisted implements UserIsWhitelisted
{
    public function __invoke(string $username) : bool
    {
        return $username !== 'realDonaldTrump';
    }
}
