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

        $variables = array();
        $pathTemplate = $this->separateUrlVariables($urlPath, $variables);
        $controllerName = $this->loadRoutes($pathTemplate);
        $nextControllerName = 'Mikroatlas\\'.self::CONTROLLERS_DIRECTORY.'\\'.$controllerName;
        $nextController = new $nextControllerName();
        return $nextController->process($variables);
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
        $controllersUrls = array_keys($iniRoutes['Controllers']);
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
     * @return string Name of the controller to call (not the full class name)
     */
    private function loadRoutes(string $path): string
    {
        $routes = parse_ini_file('routes.ini', true);
        if (!isset($routes["Routes"][$path])) {
            throw new UnexpectedValueException("The given URL wasn't found in the configuration.", 404000);
        }
        return $routes["Routes"][$path];
    }
}
