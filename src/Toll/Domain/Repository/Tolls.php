<?php
declare(strict_types=1);

namespace Toll\Domain\Repository;

use Toll\Domain\Aggregate\Toll;
use Toll\Domain\Value\TollId;

interface Tolls
{
    public function store(Toll $toll) : void;

    public function get(TollId $tollId) : Toll;
}
