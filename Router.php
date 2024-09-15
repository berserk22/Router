<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use Core\Exception;
use DI\DependencyException;
use DI\NotFoundException;
use Modules\Blog\Manager\BlogManager;
use Modules\Database\Tracy\Panel;
use Modules\I18n\Manager\I18nManager;
use Modules\Main\Manager\MainManager;
use Modules\Product\Manager\ProductManager;
use Modules\Project\Manager\ProjectManager;
use Modules\Rest\Auth\Auth;
use Modules\Rest\Auth\AuthCheck;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteRunner;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Tracy\Debugger;

class Router {

    use RouterTrait;

    /**
     * @var string
     */
    protected string $path = "";

    /**
     * @var string
     */
    private string $productManagerEntity = "Product\Manager";

    /**
     * @var string
     */
    private string $mainManagerEntity = 'Main\Manager';

    /**
     * @var string
     */
    private string $blogManagerEntity = 'Blog\Manager';

    /**
     * @param Request $request
     * @param RouteRunner $runner
     * @return ResponseInterface
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __invoke(Request $request, RouteRunner $runner): ResponseInterface {
        $this->path = $request->getUri()->getPath();

        $tmpRedirect = $this->checkRedirecting();
        if (!is_null($tmpRedirect)) {
            return $tmpRedirect;
        }

        $api = false;
        if (str_contains($this->path, '/api/')){
            $api = true;
        }

        $apiAuthController = new Auth([
            'secure' => true,
            'path' => '/api/',
            'passthrough' => '/api/v1/oauth',
            'header'=>'Authorization',
            'authenticator' => function (Request $request, Auth $auth) {
                $token = $auth->getToken($request);
                $check = new AuthCheck($this);
                if ($check->valid($token)) {
                    return $request;
                }
                else {
                    return $auth->setResponseMessage($check->getMessage());
                }
            },
            'error' => function (Request $request, Response $response, Auth $auth) {
                $output = [
                    'success' => false,
                    'error' => $auth->getResponseMessage(),
                    'code' => 401,
                ];
                return $response->withJson($output, 401);
            }
        ]);

        $tmpApcuRoute = $this->getApcuRoute($request);
        if (!is_null($tmpApcuRoute)){
            return $tmpApcuRoute;
        }

        $tmpRoute = $this->getRoute($request, $api, $apiAuthController);
        if (!is_null($tmpRoute)){
            return $tmpRoute;
        }

        throw new HttpNotFoundException($request);
    }

    /**
     * @param Request $request
     * @return Response|null
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function getApcuRoute(Request $request): Response|null {
        $routers = $this->getApcuCache()->get('routers');
        foreach ($routers as $route) {
            foreach ($route as $item) {
                if ($item['route'] === $this->path) {
                    $request = $this->setMethods($request, $item['method']);
                    $request = $this->setAttributes($request, $item['attr']);
                    $controller = $this->getContainer()->get($item['class']);
                    $next = [$controller, $item['action']];
                    $response=$this->getApp()->getResponseFactory()->createResponse();
                    return $next($request, $response);
                }
                elseif ((bool)preg_match($this->getRegex($item['route']), $this->path, $match)) {
                    if ($this->checkURL($this->path, $match)) {
                        $request = $this->setAttributes($request, $match);
                        $request = $this->setMethods($request, $item['method']);
                        $controller = $this->getContainer()->get($item['class']);
                        $next = [$controller, $item['action']];
                        $response=$this->getApp()->getResponseFactory()->createResponse();
                        return $next($request, $response);
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param Request $request
     * @param bool $api
     * @param $apiAuthController
     * @return Response|ResponseInterface|null
     */
    protected function getRoute(Request $request, bool $api, $apiAuthController): Response|ResponseInterface|null {
        $routers = $this->getApp()->getRouteCollector()->getRoutes();
        foreach ($routers as $route) {
            if ((bool)preg_match($this->getRegex($route->getPattern()), $this->path, $match) && $this->checkURL($this->path, $match)){
                $tmpRouteResponse = $this->getRouteResponse($request, $match, $api, $apiAuthController, $route);
                if (!is_null($tmpRouteResponse)){
                    return $tmpRouteResponse;
                }
            }
        }
        return null;
    }

    /**
     * @param Request $request
     * @param array $match
     * @param bool $api
     * @param $apiAuthController
     * @param $route
     * @return Response|ResponseInterface|null
     */
    protected function getRouteResponse(Request $request, array $match, bool $api, $apiAuthController, $route): Response|ResponseInterface|null {
        $check = true;
        foreach ($match as $item => $value) {
            if (!is_numeric($item)){
                $func = 'check'.ucfirst($item);
                if (!method_exists($this, $func)){
                    $check = call_user_func(function() { return true; });
                }
                elseif (!$this->$func($value)) {
                    $check = false;
                }
            }
        }
        if ($check === true) {
            $request = $this->setAttributes($request, $match);
            $response=$this->getApp()->getResponseFactory()->createResponse();
            if ($api === true) {
                return $apiAuthController->__invoke($request, $response, $route);
            }
            else {
                return $route->run($request);
            }
        }
        return null;
    }

    /**
     * @return Response|void
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkRedirecting(): Response|null {
        if (str_contains($this->path, '%')){
            $this->path = urldecode($this->path);
        }

        $this->getRedirect()->checkRedirect($this->path);
        if ($this->getRedirect()->isRedirect()){
            $response=$this->getApp()->getResponseFactory()->createResponse();
            return $this->getRedirect()->redirect($response);
        }
        return null;
    }

    /**
     * @param string $pattern
     * @param string $delimiter
     * @return string
     */
    protected function getRegex(string $pattern, string $delimiter = '/'): string {
        return $delimiter.str_replace(['{', ':', '}', '/'], ['(?<', '>', ')', '\/'], $pattern).$delimiter;
    }

    /**
     * @param string $url
     * @param array $match
     * @return bool
     */
    protected function checkURL(string $url, array $match): bool {
        foreach ($match as $value){
            $url = str_replace($value, "", $url);
        }
        $url = str_replace("/", "", $url);
        if ($url === "") {
            return true;
        }
        return false;
    }

    /**
     * @param Request $request
     * @param array $attribute
     * @return mixed
     */
    protected function setAttributes(Request $request, array $attribute): Request {
        foreach ($attribute as $key => $value) {
            if (!is_numeric($key)) {
                $request = $request->withAttribute($key, $value);
            }
        }
        return $request;
    }

    /**
     * @param Request $request
     * @param array $methods
     * @return Request
     */
    protected function setMethods(Request $request, array $methods): Request {
        if (!empty($methods)){
            foreach ($methods as $method) {
                $request = $request->withMethod($method);
            }
        }
        return $request;
    }


    /**
     * @param string $page
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkPage(string $page): bool {
        if ($this->getContainer()->has($this->mainManagerEntity)){
            /** @var MainManager $mainManager */
            $mainManager = $this->getContainer()->get($this->mainManagerEntity);
            $page_check = $mainManager->getPageEntity()::where('name', '=', $page)->first();
            if (!is_null($page_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $lang
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkLang(string $lang): bool {
        if ($this->getContainer()->has('I18n\Manager')){
            /** @var I18nManager $i18nManager */
            $i18nManager = $this->getContainer()->get('I18n\Manager');
            $lang_check = $i18nManager->getLanguageEntity()::where([
                ['code', '=', $lang],
                ['active', '=', 1]
            ])->first();
            if (!is_null($lang_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $post
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkPost(string $post): bool {
        if ($this->getContainer()->has($this->blogManagerEntity)){
            /** @var BlogManager $blogManager */
            $blogManager = $this->getContainer()->get($this->blogManagerEntity);
            $post_check = $blogManager->getBlogEntity()::where([
                ['name', '=', $post],
                ['status', '=', 'publish']
            ])->first();
            if (!is_null($post_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int|string $vendorNumber
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkVendorNumber(int|string $vendorNumber): bool {
        if ($this->getContainer()->has($this->productManagerEntity)){
            /** @var ProductManager $productManager */
            $productManager = $this->getContainer()->get($this->productManagerEntity);
            $vendorNumbercheck = $productManager->getProductEntity()::where('vendor_number', '=', $vendorNumber)->first();
            if (!is_null($vendorNumbercheck)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $category
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkCategory(string $category): bool {
        if ($this->getContainer()->has($this->productManagerEntity)){
            /** @var ProductManager $productManager */
            $productManager = $this->getContainer()->get($this->productManagerEntity);
            $category_check = $productManager->getCategoryEntity()::where('name', '=', $category)->first();
            if (!is_null($category_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $manufacturer
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkManufacturer(string $manufacturer): bool {
        if ($this->getContainer()->has($this->productManagerEntity)){
            /** @var ProductManager $productManager */
            $productManager = $this->getContainer()->get($this->productManagerEntity);
            $manufacturer_check = $productManager->getManufacturerEntity()::where('name', '=', $manufacturer)->first();
            if (!is_null($manufacturer_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $group
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkGroup(string $group): bool {
        if ($this->getContainer()->has($this->productManagerEntity)){
            /** @var ProductManager $productManager */
            $productManager = $this->getContainer()->get($this->productManagerEntity);
            $group_check = $productManager->getAttributeGroupEntity()::where('name', '=', $group)->first();
            if (!is_null($group_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkPageId(int $page_id): bool {
        if ($this->getContainer()->has($this->mainManagerEntity)){
            /** @var MainManager $mainManager */
            $mainManager = $this->getContainer()->get($this->mainManagerEntity);
            $page_check = $mainManager->getPageEntity()::find($page_id);
            if (!is_null($page_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkPostId(int $post_id): bool {
        if ($this->getContainer()->has($this->blogManagerEntity)){
            /** @var BlogManager $blogManager */
            $blogManager = $this->getContainer()->get($this->blogManagerEntity);
            $post_check = $blogManager->getBlogEntity()::find($post_id);
            if (!is_null($post_check)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkProjectId(int $project_id): bool {
        if ($this->getContainer()->has('Project\Manager')){
            /** @var ProjectManager $projectManager */
            $projectManager = $this->getContainer()->get('Project\Manager');
            $project_check = $projectManager->getProjectEntity()::find($project_id);
            if (!is_null($project_check)) {
                return true;
            }
        }
        return false;
    }

}
