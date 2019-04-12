<?php

declare(strict_types=1);

namespace Specification;

use Assert\Assert;
use Behat\Behat\Context\Context;
use Building\Domain\Aggregate\Building;
use Building\Domain\DomainEvent\CheckInAnomalyDetected;
use Building\Domain\DomainEvent\NewBuildingWasRegistered;
use Building\Domain\DomainEvent\UserCheckedIn;
use Building\Infrastructure\ReadModel\HardcodedUserIsWhitelisted;
use Prooph\EventSourcing\AggregateChanged;
use Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator;
use Prooph\EventStore\Aggregate\AggregateType;
use Rhumsaa\Uuid\Uuid;

final class CheckInCheckOut implements Context
{
    /** @var Uuid */
    private $buildingId;

    /** @var AggregateChanged[] */
    private $pastEvents = [];

    /** @var AggregateChanged[]|null */
    private $recordedEvents;

    /** @var Building|null */
    private $building;

    public function __construct()
    {
        $this->buildingId = Uuid::uuid4();
    }

    /** @Given a building was registered */
    public function a_building_was_registered() : void
    {
        $this->recordPastEvent(NewBuildingWasRegistered::occur($this->buildingId->toString(), ['name' => 'Potato']));
    }

    /** @Given /^"([^"]+)" checked into the building$/ */
    public function user_checked_into_the_building(string $username) : void
    {
        $this->recordPastEvent(UserCheckedIn::toBuilding($this->buildingId, $username));
    }

    /** @When /^"([^"]+)" checks into the building$/ */
    public function user_checks_into_the_building(string $username) : void
    {
        $this
            ->building()
            ->checkInUser($username, new HardcodedUserIsWhitelisted());
    }

    /** @Then /^"([^"]+)" should have been checked into the building$/ */
    public function should_have_been_checked_into_the_building(string $username) : void
    {
        /** @var UserCheckedIn $lastEvent */
        $lastEvent = $this->popNextRecordedEvent();

        Assert::that($lastEvent)->isInstanceOf(UserCheckedIn::class);
        Assert::that($lastEvent->username())->same($username);
    }

    /** @Then /^a check-in anomaly caused by "([^"]+)" should have been detected$/ */
    public function a_check_in_anomaly_caused_by_user_should_have_been_detected(string $username) : void
    {
        /** @var CheckInAnomalyDetected $lastEvent */
        $lastEvent = $this->popNextRecordedEvent();

        Assert::that($lastEvent)->isInstanceOf(CheckInAnomalyDetected::class);
        Assert::that($lastEvent->username())->same($username);
    }

    private function building() : Building
    {
        return $this->building
            ?? $this->building = (new AggregateTranslator())->reconstituteAggregateFromHistory(
                AggregateType::fromAggregateRootClass(Building::class),
                new \ArrayIterator($this->pastEvents)
            );
    }

    private function recordPastEvent(AggregateChanged $event) : void
    {
        $this->pastEvents[] = $event;
    }

    private function popNextRecordedEvent() : AggregateChanged
    {
        if ($this->recordedEvents === null) {
            $this->recordedEvents = (new AggregateTranslator())
                ->extractPendingStreamEvents($this->building());
        }

        return array_shift($this->recordedEvents);
    }
}
