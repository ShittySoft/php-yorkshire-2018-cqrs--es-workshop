<?php

declare(strict_types=1);

namespace Building\Infrastructure\Repository;

use Building\Domain\Aggregate\Building;
use Building\Domain\Repository\Buildings;
use Prooph\EventStore\Aggregate\AggregateRepository;
use Rhumsaa\Uuid\Uuid;
use function assert;

final class BuildingsFromAggregateRepository implements Buildings
{
    /** @var AggregateRepository */
    private $aggregateRepository;

    public function __construct(AggregateRepository $aggregateRepository)
    {
        $this->aggregateRepository = $aggregateRepository;
    }

    public function add(Building $building) : void
    {
        $this->aggregateRepository->addAggregateRoot($building);
    }

    public function get(Uuid $id) : Building
    {
        $aggregate = $this->aggregateRepository->getAggregateRoot($id->toString());

        assert($aggregate instanceof Building, 'Retrieved object should always be of type ' . Building::class);

        return $aggregate;
    }
}
