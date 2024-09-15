<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use Core\Traits\App;
use DI\DependencyException;
use DI\NotFoundException;
use Modules\Router\Manager\RouterManager;

trait RouterTrait {

    use App;

    /**
     * @return RouterManager
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getRouterManager():RouterManager {
        return $this->getContainer()->get('Router\Manager');
    }

    /**
     * @return ApcuCache|string
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getApcuCache():ApcuCache|string {
        return $this->getContainer()->get('Router\ApcuCache');
    }

    /**
     * @return Redirect
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getRedirect(): Redirect {
        return $this->getContainer()->get('Router\Redirect');
    }

}
