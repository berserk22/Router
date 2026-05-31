<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router;

use DI\DependencyException;
use DI\NotFoundException;
use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

class Redirect {

    use RouterTrait;

    /**
     * @var Model|null
     */
    private null|Model $redirect = null;

    /**
     * @var bool
     */
    private bool $isRedirect = false;

    /**
     * @param string $url
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function checkRedirect(string $url): void {
        $this->redirect = $this->getRouterManager()->getRedirectEntity()::where([
            ['path', '=', $url],
            ['status', '=', 1]
        ])->first();

        if (!is_null($this->redirect)) {
            $this->isRedirect = true;
        }
        else {
            $this->isRedirect = false;
        }
    }

    /**
     * @param Response|ResponseInterface $response
     * @return Response
     */
    public function redirect(Response|ResponseInterface $response): Response {
        return $response->withHeader('Location', $this->redirect->to)
            ->withStatus($this->redirect->code);
    }

    /**
     * @return bool
     */
    public function isRedirect(): bool {
        return $this->isRedirect;
    }

}
