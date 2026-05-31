<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use Core\Traits\App;
/*use Modules\Payment\Middleware\SubscriptionMiddleware;
use Modules\User\Middleware\CompanyMiddleware;
use Modules\User\Middleware\PermissionMiddleware;*/

class MiddlewareFactory {

    use App;

    /**
     * @param string $definition
     * @return object|null
     */
    public function create(string $definition): ?object {
        // Beispiel: 'permission:project.edit'
        /*if (str_starts_with($definition, 'permission:')) {
            $perm = str_replace('permission:', '', $definition);
            return new PermissionMiddleware($this->getContainer(), $perm);
        }

        if (str_starts_with($definition, 'company:')) {
            $feature = str_replace('company:', '', $definition);
            return new CompanyMiddleware($this->getContainer(), $feature);
        }

        if ($definition === 'subscription:check') {
            return new SubscriptionMiddleware($this->getContainer());
        }*/

        // Andere Typen wie rate-limit, etc.
        return null;
    }
}
