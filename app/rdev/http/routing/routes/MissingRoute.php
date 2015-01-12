<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Defines a route that is dispatched when the router misses on a path
 */
namespace RDev\HTTP\Routing\Routes;
use RDev\HTTP\Requests;
use RDev\HTTP\Responses;

class MissingRoute extends CompiledRoute
{
    /**
     * @param string $controllerClass The name of the controller to call
     */
    public function __construct($controllerClass)
    {
        $methods = [
            Requests\Request::METHOD_DELETE,
            Requests\Request::METHOD_GET,
            Requests\Request::METHOD_POST,
            Requests\Request::METHOD_PUT,
            Requests\Request::METHOD_HEAD,
            Requests\Request::METHOD_TRACE,
            Requests\Request::METHOD_PURGE,
            Requests\Request::METHOD_CONNECT,
            Requests\Request::METHOD_PATCH,
            Requests\Request::METHOD_OPTIONS
        ];
        $route = new Route($methods, "", ["controller" => $controllerClass . "@showHTTPError"]);
        parent::__construct(new ParsedRoute($route), true);

        $this->setDefaultValue("statusCode", Responses\ResponseHeaders::HTTP_NOT_FOUND);
    }
}