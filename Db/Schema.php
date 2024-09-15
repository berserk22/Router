<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Router\Db;

use DI\DependencyException;
use DI\NotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Modules\Database\Migration;

class Schema extends Migration {

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function create(): void {
        if (!$this->schema()->hasTable('routers')) {
            $this->schema()->create('routers', function(Blueprint $table){
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->string('name');
                $table->string('group');
                $table->json('method');
                $table->string('route');
                $table->string('class');
                $table->string('action');
                $table->json('attr');
                $table->string('status');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }

        if (!$this->schema()->hasTable('redirect')) {
            $this->schema()->create('redirect', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->string('path');
                $table->string('to');
                $table->integer('code');
                $table->integer('status');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }
    }

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function delete(): void {
        if ($this->schema()->hasTable('routers')) {
            $this->schema()->drop('routers');
        }

        if ($this->schema("main")->hasTable('redirect')) {
            $this->schema("main")->drop('redirect');
        }
    }
}
