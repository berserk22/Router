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
        if (isset($routers[$type])){
            if(str_contains($routers[$type][0]['route'], '{')){
                preg_match('/{([^*]+)}/', $routers[$type][0]['route'], $match);
                $tmp_key = explode(':', $match[1])[0];
                return str_replace($match[0], $obj[$tmp_key], $routers[$type][0]['route']);
            }
            else {
                return $routers[$type][0]['route'];
            }
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
