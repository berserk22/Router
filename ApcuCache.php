<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use DI\DependencyException;
use DI\NotFoundException;

class ApcuCache {

    use RouterTrait;

    /**
     * @var bool
     */
    private bool $apcu;

    /**
     * @var array
     */
    private array $apcuArray = [];

    /**
     * @return array|bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function init(): bool|array {
        if (function_exists('apcu_enabled')) {
            $this->apcu = apcu_enabled();
        }
        else {
            $this->apcu = false;
        }

        if (!$this->has('routers')){
            $this->add('routers', $this->getAllRouters());
        }
        else {
            $this->refresh('routers', $this->getAllRouters());
        }
        return $this->get('routers');
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        if ($this->apcu) {
            return apcu_exists($key);
        }
        else {
            return isset($this->apcuArray[$key]);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function add(string $key, mixed $value): void {
        if ($this->apcu) {
            apcu_add($key, $value);
        }
        else {
            $this->apcuArray[$key] = $value;
        }
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void {
        if ($this->apcu) {
            apcu_delete($key);
        }
        else {
            unset($this->apcuArray[$key]);
        }
    }

    /**
     * @param string $key
     * @return false|mixed
     */
    public function get(string $key): mixed {
        if ($this->apcu) {
            return apcu_fetch($key);
        }
        else {
            return $this->apcuArray[$key];
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function refresh(string $key, mixed $value): void {
        if ($this->apcu) {
            apcu_store($key, $value);
        }
        else {
            $this->apcuArray[$key] = $value;
        }
    }

    /**
     * @return array
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function getAllRouters(): array {
        $routers = $this->getRouterManager()->getRoutersEntity()::where([
            ['group', '=', 0],
            ['status', '=', 1]
        ])->get();
        $items = [];
        foreach ($routers as $route){
            $items[$route->name][] = [
                'route' => $route->route,
                'method'=> $route->getMethod(),
                'class' => $route->class,
                'action' => $route->action,
                'attr' => $route->getAttr(),
            ];
        }
        return $items;
    }

}
