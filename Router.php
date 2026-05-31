<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use DI\DependencyException;
use DI\NotFoundException;
use Modules\Blog\Manager\BlogManager;
use Modules\Main\Manager\MainManager;
use Modules\Rest\Auth\Auth;
use Modules\Rest\Auth\AuthCheck;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteRunner;

class Router {

    use RouterTrait;

    /**
     * @var string
     */
    protected string $path = "";

    /**
     * @var RequestHandlerInterface
     */
    private RequestHandlerInterface $handler;

    /**
     * @param ServerRequestInterface $request
     * @param RouteRunner $handler
     * @return ResponseInterface
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __invoke(ServerRequestInterface $request, RouteRunner $handler): ResponseInterface {
        $this->handler = $handler;

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
            'authenticator' => function (ServerRequestInterface $request, Auth $auth) {
                $token = $auth->getToken($request);
                $check = new AuthCheck($this);
                if ($check->valid($token)) {
                    return $request;
                }
                else {
                    $auth->setResponseMessage($check->getMessage());
                    return false;
                }
            },
            'error' => function (ServerRequestInterface $request, Response $response, Auth $auth) {
                $output = [
                    'success' => false,
                    'error' => $auth->getResponseMessage(),
                    'code' => 401,
                ];
                $payload = json_encode($output, JSON_UNESCAPED_UNICODE);
                $response->getBody()->write($payload);
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(401);
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

        // return $handler->handle($request);
        throw new HttpNotFoundException($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return Response|null
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function getApcuRoute(ServerRequestInterface $request): Response|null {
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
     * @param ServerRequestInterface $request
     * @param bool $api
     * @param $apiAuthController
     * @return Response|ResponseInterface|null
     */
    protected function getRoute(ServerRequestInterface $request, bool $api, $apiAuthController): Response|ResponseInterface|null {
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
     * @param ServerRequestInterface $request
     * @param array $match
     * @param bool $api
     * @param $apiAuthController
     * @param $route
     * @return Response|ResponseInterface|null
     */
    protected function getRouteResponse(ServerRequestInterface $request, array $match, bool $api, $apiAuthController, $route): Response|ResponseInterface|null {
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
     * @param string $value
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkPage(string $value): bool {
        if (!$this->getContainer()->has('Main\Manager')) {
            return false;
        }
        /** @var MainManager $manager */
        $manager = $this->getContainer()->get('Main\Manager');
        return !is_null($manager->getPageEntity()::where('name', '=', $value)->first());
    }

    /**
     * @param string $value
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function checkPost(string $value): bool {
        if (!$this->getContainer()->has('Blog\Manager')) {
            return false;
        }
        /** @var BlogManager $manager */
        $manager = $this->getContainer()->get('Blog\Manager');
        return !is_null($manager->getBlogEntity()::where('name', '=', $value)->first());
    }

    /**
     * @param string $pattern
     * @param string $delimiter
     * @return string
     */
    protected function getRegex(string $pattern, string $delimiter = '/'): string {
        return '#^' . preg_replace_callback($delimiter.'{(\w+):(\w+)}'.$delimiter, function ($matches) {
                $patterns = [
                    'UUID' => '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
                    'int' => '[0-9]+',
                    'slug' => '[a-zA-Z0-9-/_]+',
                    'any' => '[^/]+',
                ];
                $name = $matches[1];
                $type = $matches[2];
                $regex = $patterns[$type] ?? $patterns['any'];
                return '(?<' . $name . '>' . $regex . ')';
            }, $pattern) . '$#';
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
     * @param ServerRequestInterface $request
     * @param array $attribute
     * @return mixed
     */
    protected function setAttributes(ServerRequestInterface $request, array $attribute): ServerRequestInterface {
        foreach ($attribute as $key => $value) {
            if (!is_numeric($key)) {
                $request = $request->withAttribute($key, $value);
            }
        }
        return $request;
    }

    /**
     * @param ServerRequestInterface $request
     * @param array $methods
     * @return ServerRequestInterface
     */
    protected function setMethods(ServerRequestInterface $request, array $methods): ServerRequestInterface {
        if (!empty($methods)){
            foreach ($methods as $method) {
                $request = $request->withMethod($method);
            }
        }
        return $request;
    }

}
