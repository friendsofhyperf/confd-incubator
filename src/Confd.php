<?php

declare(strict_types=1);
/**
 * This file is part of friendsofhyperf/confd.
 *
 * @link     https://github.com/friendsofhyperf/confd
 * @document https://github.com/friendsofhyperf/confd/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Confd;

use FriendsOfHyperf\Confd\Driver\DriverInterface;
use FriendsOfHyperf\Confd\Driver\Etcd;
use FriendsOfHyperf\Confd\Event\ConfigChanged;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class Confd
{
    private DriverInterface $driver;

    public function __construct(private ContainerInterface $container, private ConfigInterface $config)
    {
        $driver = $this->config->get('confd.default', 'etcd');
        $class = $this->config->get(sprintf('confd.drivers.%s.driver', $driver), Etcd::class);
        $this->driver = $container->get($class);
    }

    public function fetch(): array
    {
        return $this->driver->fetch();
    }

    public function watch(): void
    {
        Coroutine::create(function () {
            $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
            $interval = (int) $this->config->get('confd.interval', 1);

            while (true) {
                $isExited = CoordinatorManager::until(Constants::WORKER_EXIT)->yield($interval);

                if ($isExited) {
                    break;
                }

                if ($this->driver->isChanged()) {
                    $eventDispatcher->dispatch(new ConfigChanged());
                }
            }
        });
    }
}
