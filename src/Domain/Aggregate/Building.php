<?php

declare(strict_types=1);

namespace Building\Domain\Aggregate;

use Building\Domain\DomainEvent\CheckInAnomalyDetected;
use Building\Domain\DomainEvent\NewBuildingWasRegistered;
use Building\Domain\DomainEvent\UserCheckedIn;
use Building\Domain\DomainEvent\UserCheckedOut;
use Building\Domain\ReadModel\UserIsWhitelisted;
use Prooph\EventSourcing\AggregateRoot;
use Rhumsaa\Uuid\Uuid;

final class Building extends AggregateRoot
{
    /** @var Uuid */
    private $uuid;

    /** @var string */
    private $name;

    /** @var array<string, null> */
    private $checkedInUsers = [];

    public static function new(string $name) : self
    {
        $self = new self();

        $self->recordThat(NewBuildingWasRegistered::occur(
            (string) Uuid::uuid4(),
            [
                'name' => $name
            ]
        ));

        return $self;
    }

    public function checkInUser(string $username, UserIsWhitelisted $whitelist) : void
    {
        if (! $whitelist->__invoke($username)) {
            throw new \DomainException(sprintf('User "%s" is not allowed to check into "%s"', $username, $this->name));
        }

        $anomalyDetected = array_key_exists($username, $this->checkedInUsers);

        $this->recordThat(UserCheckedIn::toBuilding($this->uuid, $username));

        if ($anomalyDetected) {
            $this->recordThat(CheckInAnomalyDetected::inBuilding($this->uuid, $username));
        }
    }

    public function checkOutUser(string $username) : void
    {
        $anomalyDetected = ! array_key_exists($username, $this->checkedInUsers);

        $this->recordThat(UserCheckedOut::ofBuilding($this->uuid, $username));

        if ($anomalyDetected) {
            $this->recordThat(CheckInAnomalyDetected::inBuilding($this->uuid, $username));
        }
    }

    protected function whenNewBuildingWasRegistered(NewBuildingWasRegistered $event) : void
    {
        $this->uuid = Uuid::fromString($event->aggregateId());
        $this->name = $event->name();
    }

    protected function whenUserCheckedIn(UserCheckedIn $event) : void
    {
        $this->checkedInUsers[$event->username()] = null;
    }

    protected function whenUserCheckedOut(UserCheckedOut $event) : void
    {
        unset($this->checkedInUsers[$event->username()]);
    }

    protected function whenCheckInAnomalyDetected(CheckInAnomalyDetected $event) : void
    {
        // Empty on purpose
    }

    /**
     * {@inheritDoc}
     */
    protected function aggregateId() : string
    {
        return (string) $this->uuid;
    }
}
