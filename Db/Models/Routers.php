<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router\Db\Models;

use Modules\Database\Model;

class Routers extends Model {

    protected $table = "routers";

    /**
     * @return array
     */
    public function getMethod(): array {
        if (is_array($this->method) && !empty($this->method)) {
            return $this->method;
        }
        else {
            return !is_null($this->method) ? json_decode((string)$this->method, true) : ['POST'];
        }
    }

    /**
     * @param array $method
     * @return void
     */
    public function setMethod(array $method=['POST']): void {
        $this->method=json_encode($method);
    }

    /**
     *
     */
    public function getAttr(): array {
        if (is_array($this->attr)) {
            return $this->attr;
        }
        else {
            return !empty($this->attr) ? json_decode($this->attr, true) : [];
        }
    }

    /**
     * @param array $attr
     * @return void
     */
    public function setAttr(array $attr=[]): void {
        $this->attr=json_encode($attr);
    }

}
