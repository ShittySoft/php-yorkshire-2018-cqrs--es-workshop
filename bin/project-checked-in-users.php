#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Building\Projector;

(static function () : void {
    /** @var \Psr\Container\ContainerInterface $container */
    $container = require __DIR__ . '/../container.php';

    $container->get('project-all-checked-in-users')();
})();

