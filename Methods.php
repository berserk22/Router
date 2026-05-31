<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use DI\DependencyException;
use DI\NotFoundException;

class Methods implements \Iterator {

    use RouterTrait;

    const string TYPE = 'type';

    const string INSTANCE = 'instance';

    const string METHOD = 'method';

    const string ROUTING = 'routing';

    const string GROUPS = 'groups';

    /**
     * @var array
     */
    private array $methods;

    /**
     * @var array
     */
    private array $options;

    /**
     * @var int
     */
    private int $position = 0;

    /**
     * @var array
     */
    private array $dbGroups = [];

    /**
     * @param string $type
     * @param array $methods
     * @param string $callable
     */
    public function map(string $type, array $methods, string $callable=''): void {
        foreach ($methods as $method) {
            $this->methods[] = [
                self::TYPE => $type,
                self::METHOD => $method,
                self::INSTANCE => $callable
            ];
        }
    }

    /**
     * @return mixed
     */
    public function current(): mixed {
        return $this->methods[$this->position];
    }

    /**
     * @return void
     */
    public function next(): void {
        ++$this->position;
    }

    /**
     * @return int
     */
    public function key(): int {
        return $this->position;
    }

    /**
     * @return bool
     */
    public function valid(): bool {
        return isset($this->methods[$this->position]);
    }

    /**
     * @return void
     */
    public function rewind(): void {
        $this->position = 0;
    }

    /**
     * @return void
     */
    public function resortingMethods(): void {
        $groups = [];
        $routing = [];

        foreach ($this as $method) {
            // group
            if (!isset($groups[$method['type']])) {
                $groups[$method['type']] = [];
            }
            $groups[$method['type']] = $method;

            // routing
            if (isset($routing[$method['instance']])) {
                continue;
            }
            $routing[$method['instance']] = $method;
        }

        $this->options[self::ROUTING] = $routing;
        $this->options[self::GROUPS] = $groups;
    }

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function registry(): void {
        $this->resortingMethods();

        foreach ($this->options[self::ROUTING] as $route) {
            $class = $route['instance'];
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(
                    sprintf('Router class "%s" does not exist.', $class)
                );
            }
            $this->getContainer()->set($class, new $class($this));
        }
        $dbGroups = $this->getAllGroups();
        foreach ($this->options[self::GROUPS] as $type => $group) {
            $url = isset($dbGroups[$type]) ? $dbGroups[$type]['method'] : $group['method'];
            $this->getApp()->group($url, $group['instance']);
        }
    }

    /**
     * @return array
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getAllGroups(): array {
        if (empty($this->dbGroups)){
            $routers = $this->getRouterManager()->getRoutersEntity()::where([
                ['group', '=', 1],
                ['status', '=', 1]
            ])->get();
            foreach ($routers as $route) {
                $this->dbGroups[$route->name] = [
                    'method' => $route->route,
                    'instance' => $route->class
                ];
            }
        }
        return $this->dbGroups;
    }

    /**
     * @return mixed
     */
    public function getRoutingsList(): mixed {
        return $this->options[self::GROUPS];
    }
}
