<?php

declare(strict_types=1);

namespace Building\App;

use Building\Domain\Aggregate\Building;
use Building\Domain\Command;
use Building\Domain\DomainEvent\CheckInAnomalyDetected;
use Building\Domain\DomainEvent\NewBuildingWasRegistered;
use Building\Domain\DomainEvent\UserCheckedIn;
use Building\Domain\DomainEvent\UserCheckedOut;
use Building\Domain\Repository\Buildings;
use Building\Infrastructure\Repository\BuildingsFromAggregateRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOSqlite\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\SchemaException;
use Interop\Container\ContainerInterface as InteropContainer;
use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Event\ActionEventEmitter;
use Prooph\Common\Event\ActionEventListenerAggregate;
use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\Common\Messaging\FQCNMessageFactory;
use Prooph\Common\Messaging\NoOpMessageConverter;
use Prooph\EventSourcing\AggregateChanged;
use Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator;
use Prooph\EventStore\Adapter\Doctrine\DoctrineEventStoreAdapter;
use Prooph\EventStore\Adapter\Doctrine\Schema\EventStoreSchema;
use Prooph\EventStore\Adapter\PayloadSerializer\JsonPayloadSerializer;
use Prooph\EventStore\Aggregate\AggregateRepository;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream\StreamName;
use Prooph\EventStoreBusBridge\EventPublisher;
use Prooph\EventStoreBusBridge\TransactionManager;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\ServiceLocatorPlugin;
use Psr\Container\ContainerInterface;
use Zend\ServiceManager\ServiceManager;

require_once __DIR__ . '/vendor/autoload.php';

return new ServiceManager([
    'factories' => [
        Connection::class => static function () {
            $connection = DriverManager::getConnection([
                'driverClass' => Driver::class,
                'path'        => __DIR__ . '/data/db.sqlite3',
            ]);

            try {
                $schema = $connection->getSchemaManager()->createSchema();

                EventStoreSchema::createSingleStream($schema, 'event_stream', true);

                foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
                    $connection->exec($sql);
                }
            } catch (SchemaException $ignored) {
            }

            return $connection;
        },

        EventStore::class => static function (ContainerInterface $container) {
            $eventBus   = new EventBus();
            $eventStore = new EventStore(
                new DoctrineEventStoreAdapter(
                    $container->get(Connection::class),
                    new FQCNMessageFactory(),
                    new NoOpMessageConverter(),
                    new JsonPayloadSerializer()
                ),
                new ProophActionEventEmitter()
            );

            // This listener forwards events to a listener service called exactly like:
            //
            //  * `get_class($event) . '-listeners'.
            //  * `get_class($event) . '-projectors'.
            //
            // Please note that the service must be a {{@see callable[]}}
            $eventBus->utilize(new class ($container, $container) implements ActionEventListenerAggregate
            {
                /**
                 * @var ContainerInterface
                 */
                private $eventHandlers;

                /**
                 * @var ContainerInterface
                 */
                private $projectors;

                public function __construct(
                    ContainerInterface $eventHandlers,
                    ContainerInterface $projectors
                ) {
                    $this->eventHandlers = $eventHandlers;
                    $this->projectors    = $projectors;
                }

                public function attach(ActionEventEmitter $dispatcher) : void
                {
                    $dispatcher->attachListener(MessageBus::EVENT_ROUTE, [$this, 'onRoute']);
                }

                public function detach(ActionEventEmitter $dispatcher) : void
                {
                    throw new \BadMethodCallException('Not implemented');
                }

                public function onRoute(ActionEvent $actionEvent) : void
                {
                    $messageName = (string) $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME);

                    $handlers = [];

                    $listeners  = $messageName . '-listeners';
                    $projectors = $messageName . '-projectors';

                    if ($this->projectors->has($projectors)) {
                        $handlers = array_merge($handlers, $this->eventHandlers->get($projectors));
                    }

                    if ($this->eventHandlers->has($listeners)) {
                        $handlers = array_merge($handlers, $this->eventHandlers->get($listeners));
                    }

                    if ($handlers !== []) {
                        $actionEvent->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, $handlers);
                    }
                }
            });

            (new EventPublisher($eventBus))->setUp($eventStore);

            return $eventStore;
        },

        CommandBus::class                  => static function (InteropContainer $container) : CommandBus {
            $commandBus = new CommandBus();

            // Following configuration makes sure that commands are dispatched by command handler services
            // called exactly like the command class name. The handler must be a {{@see callable}}.
            $commandBus->utilize(new ServiceLocatorPlugin($container));
            $commandBus->utilize(new class implements ActionEventListenerAggregate
            {
                public function attach(ActionEventEmitter $dispatcher) : void
                {
                    $dispatcher->attachListener(MessageBus::EVENT_ROUTE, [$this, 'onRoute']);
                }

                public function detach(ActionEventEmitter $dispatcher) : void
                {
                    throw new \BadMethodCallException('Not implemented');
                }

                public function onRoute(ActionEvent $actionEvent) : void
                {
                    $actionEvent->setParam(
                        MessageBus::EVENT_PARAM_MESSAGE_HANDLER,
                        (string) $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME)
                    );
                }
            });

            $transactionManager = new TransactionManager();
            $transactionManager->setUp($container->get(EventStore::class));

            $commandBus->utilize($transactionManager);

            return $commandBus;
        },

        // Command -> CommandHandlerFactory
        // this is where most of the work will be done (by you!)
        // Each command is a `function (CommandClass) : void`
        Command\RegisterNewBuilding::class => static function (ContainerInterface $container) : callable {
            $buildings = $container->get(Buildings::class);

            return static function (Command\RegisterNewBuilding $command) use ($buildings) {
                $buildings->add(Building::new($command->name()));
            };
        },

        Command\CheckInUser::class => static function (ContainerInterface $container) : callable {
            $buildings = $container->get(Buildings::class);

            return static function (Command\CheckInUser $command) use ($buildings) {
                $buildings
                    ->get($command->buildingId())
                    ->checkInUser($command->username());
            };
        },

        Command\CheckOutUser::class => static function (ContainerInterface $container) : callable {
            $buildings = $container->get(Buildings::class);

            return static function (Command\CheckOutUser $command) use ($buildings) {
                $buildings
                    ->get($command->buildingId())
                    ->checkOutUser($command->username());
            };
        },

        Command\NotifySecurityOfCheckInAnomaly::class => static function (ContainerInterface $container) : callable {
            return static function (Command\NotifySecurityOfCheckInAnomaly $command) {
                \error_log(sprintf(
                    'Check-in anomaly detected in building %s, caused by user %s',
                    $command->buildingId()->toString(),
                    $command->username()
                ));
            };
        },

        NewBuildingWasRegistered::class . '-projectors' => static function (ContainerInterface $container) : array {
            return [
                $container->get('project-all-checked-in-users'),
            ];
        },

        UserCheckedIn::class . '-projectors' => static function (ContainerInterface $container) : array {
            return [
                $container->get('project-all-checked-in-users'),
            ];
        },

        UserCheckedOut::class . '-projectors' => static function (ContainerInterface $container) : array {
            return [
                $container->get('project-all-checked-in-users'),
            ];
        },

        CheckInAnomalyDetected::class . '-listeners' => static function (ContainerInterface $container) : array {
            $commandBus = $container->get(CommandBus::class);

            return [
                function (CheckInAnomalyDetected $event) use ($commandBus) : void {
                    $commandBus->dispatch(Command\NotifySecurityOfCheckInAnomaly::inBuilding(
                        $event->buildingId(),
                        $event->username()
                    ));
                },
            ];
        },

        'project-all-checked-in-users' => function (ContainerInterface $container) : callable {
            $eventStore = $container->get(EventStore::class);

            return static function () use ($eventStore) : void {
                /** @var AggregateChanged[] $allHistory */
                $allHistory = $eventStore->loadEventsByMetadataFrom(
                    new StreamName('event_stream'),
                    [
                        'aggregate_type' => Building::class,
                    ]
                );

                $allBuildings = [];

                foreach ($allHistory as $event) {
                    if (! array_key_exists($event->aggregateId(), $allBuildings)) {
                        $allBuildings[$event->aggregateId()] = [];
                    }

                    if ($event instanceof UserCheckedIn) {
                        $allBuildings[$event->aggregateId()][$event->username()] = null;
                    }

                    if ($event instanceof UserCheckedOut) {
                        unset($allBuildings[$event->aggregateId()][$event->username()]);
                    }
                }

                array_walk($allBuildings, function (array $usernames, string $buildingId) : void {
                    file_put_contents(__DIR__ . '/public/' . $buildingId . '.json', json_encode(array_keys($usernames)));
                });
            };
        },


        // Our concrete repository implementation
        Buildings::class                   => static function (ContainerInterface $container
        ) : Buildings {
            return new BuildingsFromAggregateRepository(
                new AggregateRepository(
                    $container->get(EventStore::class),
                    AggregateType::fromAggregateRootClass(Building::class),
                    new AggregateTranslator()
                )
            );
        },
    ],
]);
