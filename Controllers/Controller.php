<?php

namespace Mikroatlas\Controllers;

use InvalidArgumentException;
use Mikroatlas\Models\CacheManager;
use Mikroatlas\Models\Sanitizable;
use Mikroatlas\Models\UserException;

/**
 * Abstract class that all controllers need to extend
 * @author Jan Štěch
 */
abstract class Controller
{
    public const VIEWS_DIRECTORY = 'Views';
    protected const CONTROLLERS_DIRECTORY = 'Controllers';

    /**
     * @var bool $cachedResponse
     * If set to TRUE, the website can be loaded out of a cache file instead of being generated from the views
     * If set to FALSE, no cache is available and the website needs to be generated out of views
     */
    protected static bool $cachedResponse = false;

    /**
     * @var string|null Name of the cache file to load from/save into the website
     */
    protected static ?string $cacheFileId = null;

    /**
     * @var array $data
     * Array containing data to use to fill the blanks in the views.
     * This variable should be an associative array of arrays, where keys in the outer array are the names
     * of the views into which the data from the inner arrays should be inserted.
     * Variables that shouldn't be sanitized against XSS attacks should start with a lowercase letter
     * For example:
     * [
     *     "layout" => [
     *         "Username" => "Admin",
     *         "year" => "2023"
     *     ],
     *     "article" => [
     *         "Title" => "Header",
     *         "article" => "<p>This is the text.</p>"
     *     ]
     * ]
     */
    protected static array $data = [];

    /**
     * @var array $views
     * Array containing the list of views that should be used to generate the webpage for which
     * the controller is responsible.
     * The order of the views matters, they will be uses sequentially.
     */
    protected static array $views = [];

    /**
     * @var array $cssFiles
     * Array containing the list of CSS stylesheets that should be used to generate the webpage for which
     * the controller is responsible.
     * All CSS files specified here are included at the same place, in their respective order
     */
    protected static array $cssFiles = [];

    /**
     * @var array $jsFiles
     * Array containing the list of JavaScript source files that should be used to generate the webpage for which
     * the controller is responsible.
     * All JS files specified here are included at the same place, in their respective order
     */
    protected static array $jsFiles = [];

    /**
     * Method responsible for getting the data from models and then generating the webpage.
     * @param array $args Array of arguments for the function, not all controller need to use this, default empty array
     * @return int HTTP response code to return to the client
     * @throws UserException In case the request is invalid
     */
    public abstract function process(array $args = []): int;

    /**
     * Method setting the currently processed request as API request with JSON response.
     * This means that no views are used (only a basic "empty" one (json.phtml)), Content-type header is set
     * and response is prepared to be outputted.
     * @param string $jsonString JSON response for the current request
     * @return void
     */
    public function setJsonResponse(string $jsonString): void
    {
        header('Content-type: application/json');
        self::$views = ['json'];
        self::$data['json']['response'] = $jsonString;
    }

    /**
     * Method unpacking the view data and loading all the views.
     * THIS METHOD IS WRITING OUTPUT INTO THE WEBPAGE.
     * Because of this, it should be called at the very end of the request processing protocol.
     * @return bool FALSE in case an error has occurred during the data-sanitization process.
     * @throws InvalidArgumentException If anti-XSS sanitization fails
     */
    public function generate(): bool
    {
        $cManager = new CacheManager();

        if (self::$cachedResponse) {
            //Load cached website
            $cacheFile = $cManager->getCacheFile(self::$cacheFileId);
            readfile($cacheFile);
            return true;
        }

        if (!is_null(self::$cacheFileId)) {
            ob_start();
        }

        $this->unpackViewData();

        if (!is_null(self::$cacheFileId)) {
            $cManager->saveCache(self::$cacheFileId, ob_get_contents(), false);
            ob_end_flush();
        }

        return true;
    }

    /**
     * Method sanitizing and then unpacking the data for views into individual variables.
     * When done, it loads the outermost view and all subsequent views too.
     * No other methods should be called after this method is called, because output into the webpage is written.
     * @return void
     * @throws InvalidArgumentException In case the sanitization of at least one variable fails.
     */
    private function unpackViewData(): void
    {
        $sanitizedValues = [];
        foreach (self::$data as $viewName => $viewData) {
            foreach ($viewData as $key => $value) {
                //Sanitize against XSS attack
                if (ord($key[0]) <= 90 && ord($key[0]) >= 65) { //Uppercase letters
                    $sanitizedValue = $this->antiXssSanitizazion($value);
                    $sanitizedValueName = $viewName.'_'.strtolower($key[0]).substr($key, 1); //Convert key name to camelCase
                } else {
                    $sanitizedValue = $value;
                    $sanitizedValueName = $viewName.'_'.$key; //Convert key name to camelCase
                }
                $sanitizedValues[$sanitizedValueName] = $sanitizedValue;
            }
        }

        //Unpack the array
        extract($sanitizedValues);

        //Start loading the views
        require self::VIEWS_DIRECTORY.'/'.array_shift(self::$views).'.phtml';
    }

    /**
     * Method sanitizing the provided argument against XSS attack
     * @param mixed $data Variable to sanitize
     * @return int|double|bool|null|string|array|Sanitizable The sanitized value
     * @throws InvalidArgumentException If the provided value couldn't be sanitized
     */
    private function antiXssSanitizazion($data)
    {
        switch (gettype($data)) {
            case 'integer':
            case 'double':
            case 'boolean':
            case 'NULL':
                return $data;
            case 'string':
                return htmlspecialchars($data, ENT_QUOTES);
            case 'array':
                $sanitized = [];
                foreach ($data as $key => $value) {
                    $sanitized[$this->antiXssSanitizazion($key)] = $this->antiXssSanitizazion($value);
                }
                return $sanitized;
            case 'object':
                if ($data instanceof Sanitizable) {
                    $data->sanitize();
                } else {
                    throw new InvalidArgumentException('Couldn\'t sanitize object of type '.get_class($data).' because it doesn\'t implement the "sanitize()" method.', 500002);
                }
                return $data;
            default:
                throw new InvalidArgumentException('Couldn\'t sanitize variable of type '.gettype($data).' against XSS attack.', 500001);
        }
    }

    /**
     * Method sending a classical "Location" redirect leading to a 302 (Found) response code.
     * Script execution is stopped within this method with exit()
     * @param string $location Location to redirect to
     * @return never
     */
    protected function redirect(string $location)
    {
        header('Location: '.$location);
        exit();
    }
}

