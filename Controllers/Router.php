<?php

namespace Mikroatlas\Controllers;

use BadMethodCallException;
use UnexpectedValueException;

/**
 * @see Controller
 */
class Router extends Controller
{
    private const ROUTES_INI_FILE = 'routes.ini';

    /**
     * @inheritDoc
     * The first element in the array needs to be the requested URL
     * @return int HTTP response code
     * @throws BadMethodCallException If no URL was passed in the parameter array
     */
    public function process(array $args = []): int
    {
        if (empty($args) || empty($args[0])) {
            throw new BadMethodCallException("No URL to process was provided", 500003);
        }

        $url = array_shift($args);
        $urlPath = parse_url($url, PHP_URL_PATH);
        $urlPath = trim($urlPath, '/'); //Remove the trailing slash (if present)
        $urlPath = '/'.$urlPath;

        self::$views[] = 'layout';
        self::$cssFiles[] = 'layout';
        self::$jsFiles[] = 'layout';

        $variables = array(); //Variable arguments provided in the URL
        $parameters = array(); //Order of the arguments that needs to be passed to the controller
        $pathTemplate = $this->separateUrlVariables($urlPath, $variables);
        $controllerName = $this->loadRoutes($pathTemplate, $parameters);
        $arguments = $this->fillInArguments($variables, $parameters);
        $nextControllerName = 'Mikroatlas\\'.self::CONTROLLERS_DIRECTORY.'\\'.$controllerName;
        $nextController = new $nextControllerName();
        return $nextController->process($arguments);
    }

    /**
     * Method separating absolute URL path values from variables and returning the URL path with variables replaced
     * with placeholders
     * @param string $urlPath URL path requested by the client
     * @param array $variables An empty array passed by reference, which is filled by the variable values during this
     * method's execution
     * @return string The URL path with variables replaced by <n> placeholders (where n is a whole number starting at 0)
     */
    private function separateUrlVariables(string $urlPath, array &$urlVariablesValues) : string
    {
        $urlPath = trim($urlPath, '/');

        if (empty($urlPath)) {
            $urlArguments = [];
        } else {
            $urlArguments = explode('/', $urlPath);
        }

        $iniRoutes = parse_ini_file(self::ROUTES_INI_FILE, true);
        $controllersUrls = array_keys($iniRoutes['Keywords']);
        $urlVariablesArr = array_diff($urlArguments, $controllersUrls); //Array of URL parameters only
        $urlVariablesPositions = array_keys($urlVariablesArr);
        $urlVariablesValues = array_values($urlVariablesArr);
        for ($i = 0, $j = 0; $i < count($urlArguments); $i++) {
            if (in_array($i, $urlVariablesPositions)) {
                $urlArguments[$i] = '<'.$j.'>';
                $j++; //Variable order
            }
        }

        return '/'.implode('/', $urlArguments);
    }

    /**
     * Method loading the routes.ini file and searching for the correct controller to use, depending on the parameter
     * @param string $path The URL path of the request (used to search for the controller to use)
     * @param array $arguments An array that will be filled with the parameters for the controller, with placeholders for variable arguments
     * @return string Name of the controller to call (not the full class name)
     */
    private function loadRoutes(string $path, array &$arguments): string
    {
        $routes = parse_ini_file('routes.ini', true);
        if (!isset($routes["Routes"][$path])) {
            throw new UnexpectedValueException("The given URL ($path) wasn't found in the configuration.", 404000);
        }
        $controllerWithArgumentsPlaceholder = $routes["Routes"][$path];
        $controllerName = explode('?', $controllerWithArgumentsPlaceholder)[0];
        if (isset(explode('?', $controllerWithArgumentsPlaceholder)[1])) {
            $arguments = explode(',', explode('?', $controllerWithArgumentsPlaceholder)[1]);
        } else {
            $arguments = [];
        }

        return $controllerName;
    }

    private function fillInArguments(array $variables, array $parameters)
    {
        $result = [];
        foreach ($parameters as $parameter) {
            if (preg_match('/<\d*>/', $parameter)) {
                $result[] = array_shift($variables);
            } else {
                $result[] = $parameter;
            }
        }
        return $result;
    }
}

