<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router\Plugins;

use DI\DependencyException;
use DI\NotFoundException;
use Modules\Router\ApcuCache;
use Modules\View\AbstractPlugin;

class GetUrl extends AbstractPlugin {

    /**
     * @param string $type
     * @param array|string|null $obj
     * @return string
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function process(string $type, array|string $obj = null): string {
        $routers = $this->getApcuCache()->get('routers');
        if (empty($obj)) {
            $obj = [];
        }
        if (isset($routers[$type])) {
            $route = $routers[$type][0]['route'];
            // Überprüfen, ob Platzhalter wie `{parameter:regex}` vorhanden sind
            if (str_contains($route, '{')) {
                // Ersetze die Platzhalter in einem Schritt
                $route = preg_replace_callback(
                    "/{(\w+):[^\}]+}/",
                    function ($matches) use ($obj) {
                        // `$matches[1]` enthält den Namen des Platzhalters, z.B. `carInfo`
                        $paramName = $matches[1];
                        return $obj[$paramName] ?? $matches[0]; // Ersetze durch Wert aus `$obj` oder behalte Platzhalter bei
                    },
                    $route
                );
            }

            return $route;
        }
        else {
            return $this->getApp()->getRouteCollector()->getRouteParser()->urlFor($type, $obj);
        }
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getApcuCache(): ApcuCache|string {
        return $this->getContainer()->get('Router\ApcuCache');
    }

}
