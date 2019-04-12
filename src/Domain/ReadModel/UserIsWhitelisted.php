<?php

declare(strict_types=1);

namespace Building\Domain\ReadModel;

interface UserIsWhitelisted
{
    public function __invoke(string $username) : bool;
}
