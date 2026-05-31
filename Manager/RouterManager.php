<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router\Manager;

use Core\Traits\App;
use DI\DependencyException;
use DI\NotFoundException;

class RouterManager {

    use App;

    /**
     * @var string
     */
    private string $routers = "Router\Db\Routers";

    /**
     * @var string
     */
    private string $redirect = "Router\Db\Redirect";

    /**
     * @return RouterManager
     */
    public function initEntity(): static {
        if (!$this->getContainer()->has($this->routers)){
            $this->getContainer()->set($this->routers, 'Modules\Router\Db\Models\Routers');
        }

        if (!$this->getContainer()->has($this->redirect)){
            $this->getContainer()->set($this->redirect, 'Modules\Router\Db\Models\Redirect');
        }
        return $this;
    }

    /**
     * @return string
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getRoutersEntity(): string {
        return $this->getContainer()->get($this->routers);
    }

    /**
     * @return string
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getRedirectEntity(): string {
        return $this->getContainer()->get($this->redirect);
    }

}
