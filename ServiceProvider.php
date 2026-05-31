<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use Core\Module\Provider;
use DI\DependencyException;
use DI\NotFoundException;
use Modules\Database\MigrationCollection;
use Modules\Router\Db\Schema;
use Modules\Router\Manager\RouterManager;
use Modules\View\PluginManager;
use Modules\View\ViewManager;

class ServiceProvider extends Provider {

    /**
     * @var array|string[]
     */
    protected array $plugins = [
        'getUrl' => '\Modules\Router\Plugins\GetUrl'
    ];

    /**
     * @var string
     */
    private string $methods = "Router\Methods";

    /**
     * @return void
     */
    public function beforeInit(): void {
        $container = $this->getContainer();
        if (!$container->has($this->methods)){
            $container->set($this->methods, new Methods($this));
        }

        if (!$container->has('Router\Manager')){
            $container->set('Router\Manager', (new RouterManager($this))->initEntity());
        }

        if (!$container->has('Router\Redirect')){
            $container->set('Router\Redirect', new Redirect($this));
        }

        if (!$container->has("MiddlewareFactory")){
            $container->set('MiddlewareFactory', new MiddlewareFactory($this));
        }
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function init(): void {
        $container = $this->getContainer();

        if ($container->has('ViewManager::View')){
            /** @var $viewer ViewManager */
            $viewer = $container->get('ViewManager::View');
            $plugins = function(){
                $pluginManager = new PluginManager();
                $pluginManager->addPlugins($this->plugins);
                return $pluginManager->getPlugins();
            };
            $viewer->setPlugins($plugins());
        }

        if (!$container->has('Router\ApcuCache')) {
            $cache = new ApcuCache($this);
            $cache->init();
            $container->set('Router\ApcuCache', $cache);
        }
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function afterInit(): void {
        $container = $this->getContainer();

        if ($container->has('Modules\Database\ServiceProvider::Migration::Collection')) {
            /* @var $databaseMigration MigrationCollection  */
            $container->get('Modules\Database\ServiceProvider::Migration::Collection')->add(new Schema($this));
        }
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function register(): void{
        $container = $this->getContainer();
        if ($container->has($this->methods)) {
            $container->get($this->methods)->registry();
        }
        $this->getApp()->add(new Router($this));
    }
}
