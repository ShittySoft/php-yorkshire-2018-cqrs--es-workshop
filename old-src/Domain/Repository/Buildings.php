<?php

declare(strict_types=1);

namespace Building\Domain\Repository;

use Building\Domain\Aggregate\Building;
use Rhumsaa\Uuid\Uuid;

interface Buildings
{
    public function add(Building $building) : void;
    public function get(Uuid $id) : Building;
}
