<?php

declare(strict_types=1);

namespace Building\App;

use function assert;
use Building\Domain\Command;
use Prooph\ServiceBus\CommandBus;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rhumsaa\Uuid\Uuid;
use Zend\Diactoros\Response as DiactorosResponse;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\RouteMiddleware;
use Zend\Expressive\Router\RouteCollector;
use Zend\HttpHandlerRunner\Emitter\SapiEmitter;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\MiddlewarePipe;

call_user_func(function () {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    $serviceContainer = require __DIR__ . '/../container.php';

    //////////////////////////
    // Routing/frontend/etc //
    //////////////////////////

    $pipeline = new MiddlewarePipe();
    $router   = new FastRouteRouter();

    $app = new Application(
        new MiddlewareFactory(new MiddlewareContainer($serviceContainer)),
        $pipeline,
        new RouteCollector($router),
        new RequestHandlerRunner(
            $pipeline,
            new SapiEmitter(),
            [ServerRequestFactory::class, 'fromGlobals'],
            static function () {
                die('Failed to instantiate response');
            }
        )
    );

    //////////////////////////////////////////////////////////
    // Error handling, renders errors (in a simplistic way) //
    //////////////////////////////////////////////////////////

    $app->pipe(new class implements MiddlewareInterface {
        public function process(Request $request, RequestHandlerInterface $handler) : Response
        {
            try {
                return $handler->handle($request);
            } catch (\Throwable $exception) {
                $errorMessages = [];

                do {
                    $errorMessages[] = 'Error #' . count($errorMessages) . ': ' . get_class($exception) . "\n"
                        . 'Message: ' . $exception->getMessage() . "\n"
                        . 'Trace: ' . $exception->getTraceAsString() . "\n";

                    $exception = $exception->getPrevious();
                } while ($exception !== null);

                $response = (new DiactorosResponse())
                    ->withStatus(500);

                $response->getBody()->write(implode("\n-------\nPREVIOUS:\n", $errorMessages));

                return $response;
            }
        }
    });

    $app->pipe(new RouteMiddleware($router));

    //////////////////////////////////////////////////////////////
    // Actual routes here: from here on, it's application logic //
    //////////////////////////////////////////////////////////////


    $app->get('/', new class implements RequestHandlerInterface {
        public function handle(Request $request) : Response
        {
            ob_start();
            require __DIR__ . '/../template/index.php';
            $content = ob_get_clean();

            assert(is_string($content));

            $response = new DiactorosResponse();

            $response->getBody()->write($content);

            return $response;
        }
    });

    $app->post('/register-new-building', new class ($serviceContainer->get(CommandBus::class)) implements RequestHandlerInterface {
        /** @var CommandBus */
        private $commandBus;

        public function __construct(CommandBus $commandBus)
        {
            $this->commandBus = $commandBus;
        }

        public function handle(Request $request) : Response
        {
            $post = $request->getParsedBody();

            assert(is_array($post));

            $this->commandBus->dispatch(Command\RegisterNewBuilding::fromName($post['name']));

            return (new DiactorosResponse())
                ->withStatus(302)
                ->withAddedHeader('Location', '/');
        }
    });

    $app->get('/building/{buildingId}', new class () implements RequestHandlerInterface {
        public function handle(Request $request) : Response
        {
            $buildingId = Uuid::fromString($request->getAttribute('buildingId'));

            ob_start();
            require __DIR__ . '/../template/building.php';
            $content = ob_get_clean();

            assert(is_string($content));

            $response = new DiactorosResponse();

            $response->getBody()->write($content);

            return $response;
        }
    });

    $app->post('/checkin/{buildingId}', new class ($serviceContainer->get(CommandBus::class)) implements RequestHandlerInterface {
        /** @var CommandBus */
        private $commandBus;

        public function __construct(CommandBus $commandBus)
        {
            $this->commandBus = $commandBus;
        }

        public function handle(Request $request) : Response
        {
            $buildingId = Uuid::fromString($request->getAttribute('buildingId'));

            $post = $request->getParsedBody();

            assert(is_array($post));

            $this->commandBus->dispatch(Command\CheckInUser::toBuilding($buildingId, $post['username']));

            return (new DiactorosResponse())
                ->withStatus(302)
                ->withAddedHeader('Location', '/building/' . $buildingId->toString());
        }
    });


    $app->post('/checkout/{buildingId}', new class ($serviceContainer->get(CommandBus::class)) implements RequestHandlerInterface {
        /** @var CommandBus */
        private $commandBus;

        public function __construct(CommandBus $commandBus)
        {
            $this->commandBus = $commandBus;
        }

        public function handle(Request $request) : Response
        {

        }
    });

    $app->pipe(new DispatchMiddleware());

    $app->run();
});
