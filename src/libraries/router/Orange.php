<?php

namespace projectorangebox\orange\library\router;

use projectorangebox\orange\library\exceptions\Internal\ArrayKeyNotFoundException;
use projectorangebox\orange\library\exceptions\Internal\ParameterException;
use projectorangebox\orange\library\exceptions\MVC\RouterException;

/* Total Rewrite therefore we are NOT extending */

class Orange
{
	/**
	 * Current class name
	 *
	 * @var	string
	 */
	public $class = '';

	/**
	 * Current method name
	 *
	 * @var	string
	 */
	public $method =	'';

	/**
	 * Sub-directory that contains the requested controller class
	 *
	 * @var	string
	 */
	public $directory = '';

	/**
	 * List of routes
	 *
	 * @var	array
	 */
	protected $routes = [];

	/**
	 * $url
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * current request method
	 *
	 * @var string
	 */
	protected $requestMethod = '';

	/**
	 * $requestType
	 *
	 * @var string
	 */
	protected $requestType = '';

	/**
	 * $backUpLevels
	 *
	 * @var undefined
	 */
	protected $backUpLevels = '../..';

	/**
	 * $defaultMethod
	 *
	 * @var undefined
	 */
	protected $defaultMethod = 'index';

	/**
	 * $processMiddleware
	 *
	 * @var boolean
	 */
	protected $onResponse = true;

	/**
	 * $onRequest
	 *
	 * @var boolean
	 */
	protected $onRequest = true;

	/**
	 * $allMethods
	 *
	 * @var string
	 */
	protected $allMethods = 'get,post,cli,put,delete';

	/**
	 * Class constructor
	 *
	 * Runs the route mapping function.
	 *
	 * @param	array	$routing
	 * @return	void
	 */
	public function __construct()
	{
		log_message('info', 'Initializing Orange Route Class');

		/* reference to CodeIgniter URI Object */
		$uri = load_class('URI');
		$input = load_class('Input');

		$this->url = implode('/', $uri->segments);

		log_message('info', sprintf('Route: URI: "%s".', $this->url));

		$this->requestMethod = $input->get_http_method(); /* http method (get,put,post,patch,delete... or cli */
		$this->requestType = $input->get_request_type(); /* http, cli, ajax */

		log_message('info', sprintf('Route: the HTTP request method is "%s" the request type is "%s".', $this->requestMethod, $this->requestType));

		/* load our routes from the routes configuration file */
		$this->loadRouterConfig();

		/* convert the URL to Controller / Method */
		list($callback, $params) = $this->dispatch($this->getSearch('routes'));

		/* if it's a closure call it */
		/* -- not working because of cache
		if (is_callable($callback)) {
			$callback = call_user_func_array($callback,$params);
		}
		*/

		log_message('info', sprintf('Route: the found call back is "%s".', $callback));

		$this->setDirectoryClassMethod($callback);

		$uri->rsegments = array_merge([1 => $this->fetch_class(), 2 => $this->fetch_method()], $params);

		log_message('info', 'Orange Route Class Initialized');
	}

	/**
	 *
	 * controller::method
	 * /folder/folder/admin/controller::method
	 * /packages/orange/module/controller/folder/folder/admin/controller::method
	 *
	 * ++ start at APPPATH/Controllers
	 * $route['welcome/index'] = 'welcome::index';
	 * $route['welcome/index2'] = 'folder1/folder2/admin/welcome::index';
	 *
	 * ++ start at __ROOT__
	 * Notice the starting forward slash /
	 * $route['welcome/index3'] = '/packages/orange/module/controller/folder/folder/admin/controller::method';
	 *
	 */
	protected function setDirectoryClassMethod(string $callback): void
	{
		if ($callback[0] == '/') {
			/* start at the __ROOT__ level to find the controller file */
			$segs = explode('/', $callback);
			$classMethod = array_pop($segs);
			$directory = $this->backUpLevels . implode('/', $segs);
		} else {
			/* start at APPPATH Controllers folder to find the controller file */
			$segs = explode('/', $callback);
			$classMethod = array_pop($segs);
			$directory = implode('/', $segs);
		}

		list($c, $m) = explode('::', $classMethod);

		$this->set_directory($directory);
		$this->set_class($c);
		$this->set_method($m);
	}

	/**
	 * loadConfig
	 *
	 * @return void
	 */
	protected function loadRouterConfig(): void
	{
		/* !todo CACHE hum... what about closure routes? https://github.com/brick/varexporter */

		/* low level because config not even fully loaded */
		$configuration = \loadConfigFile('config');

		/* where is the cache file? */
		$cacheFilePath = $configuration['cache_path'] . 'routes.php';

		/* are we in development mode or is the cache file missing */
		if (ENVIRONMENT == 'development' || !file_exists($cacheFilePath)) {
			/* setup defaults */
			$config['back up levels to root'] = '../..';
			$config['default method'] = 'index';
			$config['request middleware on'] = true;
			$config['response middleware on'] = true;

			if (file_exists(APPPATH . 'config/routes.php')) {
				include(APPPATH . 'config/routes.php');
			}

			if (file_exists(APPPATH . 'config/' . ENVIRONMENT . '/routes.php')) {
				include(APPPATH . 'config/' . ENVIRONMENT . '/routes.php');
			}

			/* grab the config values */
			$this->allMethods = $config['all'] ?? $this->allMethods;
			$this->backUpLevels = $config['back up levels to root'] ?? $this->backUpLevels;
			$this->defaultMethod = $config['default method'] ?? $this->defaultMethod;
			$this->onRequest = $config['request middleware on'] ?? $this->onRequest;
			$this->onResponse = $config['response middleware on'] ?? $this->onResponse;

			/* reformat */
			$config['routeto'] = $this->buildRouteToArray($config['routes'], 'get');
			$config['routes'] = $this->buildArray($config['routes'], $this->defaultMethod);

			$config['request'] = (!isset($config['request'])) ? [] : $this->buildArray($config['request'], 'request');
			$config['response'] = (!isset($config['response'])) ? [] : $this->buildArray($config['response'], 'response');

			log_message('debug', 'Route: Build Router Cache File ' . $cacheFilePath);

			\FS::var_export_file($cacheFilePath, $config);
		} else {
			$config = include $cacheFilePath;
		}

		$this->routes['routes'] = $config['routes'];
		$this->routes['request'] = $config['request'];
		$this->routes['response'] = $config['response'];
		$this->routes['routeto'] = $config['routeto'];
	}

	/**
	 * buildArray
	 *
	 * @param array $routes
	 * @param string $key
	 * @return void
	 */
	protected function buildArray(array $routes, string $defaultMethod): array
	{
		$attachMethod = function ($input, $method) {
			return (is_string($input) && strpos($input, '::') === false) ? $input .= '::' . $method : $input;
		};

		$built = [];

		foreach ($routes as $route) {
			switch (count($route)) {
				case 2:
					$url = $route[0];
					$httpMethod = 'get';
					$callback = $route[1];
					break;
				case 3:
					$url = $route[0];
					$httpMethod = $route[1];
					$callback = $route[2];
					break;
				default:
					echo 'Route Configuration (routes.php) Incorrect number of parameters ' . print_r($route, true) . '.' . PHP_EOL;
					exit(1);
			}

			/* if they didn't provide a method use the default */
			if (is_array($callback)) {
				foreach ($callback as $idx => $single) {
					$callback[$idx] = $attachMethod($single, $defaultMethod);
				}
			} elseif (is_string($callback)) {
				$callback = $attachMethod($callback, $defaultMethod);
			}

			if ($httpMethod == '*') {
				$httpMethod = $this->allMethods;
			}

			if (\is_string($httpMethod)) {
				$httpMethod = explode(',', $httpMethod);
			}

			foreach ($httpMethod as $hm) {
				$built[strtolower($hm)]['#^' . str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $url) . '$#'] = $callback;
			}
		}

		return $built;
	}

	protected function buildRouteToArray(array $routes, string $defaultMethod): array
	{
		$routeTo = [];

		foreach ($routes as $route) {
			/* incorrect format */
			if (!is_array($route)) {
				throw new RouterException($route . ' is not an array. Check the format of your routes.php configuration file.');
			}

			switch (count($route)) {
				case 2:
					$sectionRoute = $route[0];
					$sectionHttpMethod = $defaultMethod;
					$sectionMatch = $route[1];
					break;
				case 3:
					$sectionRoute = $route[0];
					$sectionHttpMethod = $route[1];
					$sectionMatch = $route[2];
					break;
			}

			/* normalize */
			$routeTo[strtolower($sectionMatch)] = $sectionRoute;
		}

		return $routeTo;
	}

	/**
	 * dispatch
	 * Dispatch the request to the appropriate route(s)
	 *
	 * @param string $uri
	 * @param string $requestMethod
	 * @return array
	 */
	protected function dispatch(array $search): array
	{
		$matched = [];

		foreach ($search as $regxUrl => $callback) {
			if (preg_match($regxUrl, $this->url, $params)) {
				/* match */
				$this->matched = $regxUrl;

				/* add our own custom parameters */
				$params['T'] = ucfirst($this->requestType); /* Http, Cli, Ajax */
				$params['M'] = ucfirst($this->requestMethod); /* http method - Get, Put, Post, Patch, Delete... or Cli */

				/* replace arguments with params */
				foreach ($params as $key => $value) {
					$callback = str_replace('$' . $key, $value, $callback);
				}

				/* shift off the url */
				array_shift($params);

				$matched = [$callback, $params];

				break; /* break out of foreach loop */
			}
		}

		if (empty($matched)) {
			throw new \Exception('No Catch All Route Provided.');
		}

		return $matched;
	}

	/**
	 * getSearch
	 *
	 * @param string $key
	 * @return void
	 */
	protected function getSearch(string $key): array
	{
		return (isset($this->routes[$key][$this->requestMethod]) && is_array($this->routes[$key][$this->requestMethod])) ? $this->routes[$key][$this->requestMethod] : [];
	}

	/**
	 * set_class
	 *
	 * @param string $class
	 * @return void
	 */
	public function set_class(string $class): void
	{
		$this->class = str_replace('-', '_', trim($class, '/'));
	}

	/**
	 * fetch_class
	 *
	 * @return void
	 */
	public function fetch_class(): string
	{
		return $this->class;
	}

	/**
	 * set_method
	 *
	 * @param string $method
	 * @return void
	 */
	public function set_method(string $method): void
	{
		$method = str_replace('-', '_', trim($method, '/'));

		$this->method = (empty($method)) ? 'index' : $method;
	}

	/**
	 * fetch_method
	 *
	 * @return void
	 */
	public function fetch_method(): string
	{
		return $this->method;
	}

	/**
	 * set_directory
	 *
	 * @param mixed string
	 * @return void
	 */
	public function set_directory(string $directory = null): void
	{
		$this->directory = (empty($directory)) ? '' : trim($directory, '/') . '/';
	}

	/**
	 * fetch_directory
	 *
	 * @return void
	 */
	public function fetch_directory(): string
	{
		return $this->directory;
	}

	/**
	 * onRequest
	 *
	 * @param CI_Input &$input
	 * @return void
	 */
	public function onRequest(\CI_Input &$input): void
	{
		if ($this->onRequest) {
			$this->on($this->getSearch('request'), $input);
		}
	}

	/**
	 * onResponse
	 *
	 * @param string &$output
	 * @return void
	 */
	public function onResponse(string &$output): void
	{
		if ($this->onResponse) {
			$this->on($this->getSearch('response'), $output);
		}
	}

	/**
	 * on
	 *
	 * @param string $method
	 * @param mixed &$reference
	 * @return void
	 */
	protected function on(array $search, &$reference): void
	{
		try {
			list($callback, $params) = $this->dispatch($search);

			foreach ($callback as $classMethod) {
				list($classname, $method) = explode('::', $classMethod, 2);
				if (class_exists($classname, true)) {
					$middleware = new $classname();
					if (method_exists($middleware, $method)) {
						if ($middleware->$method($reference, $params) === false) {
							break; /* break out of foreach */
						}
					}
				} else {
					throw new \Exception('Middleware Class "' . $classname . '" Not Found.');
				}
			}
		} catch (\Exception $e) {
			/**
			 * doesn't matter if no matches found
			 * so disreguard any thrown errors from dispatch method
			 */
		}
	}

	/**
	 *
	 * If your route is ['test/(:num)','*','Test::test$1'] for example
	 * you would search for 'Test::test$1'
	 * this would return 'test/(:num)'
	 * which is used to create the path 'test/(:num)' => test/option1
	 * using: ci('router')->routeTo('Test::test$1','option1');
	 *
	 * @param string $search
	 * @param $params
	 * @return string
	 */
	public function routeTo(string $search, ...$params): string
	{
		$parameters = \func_get_args();

		$search = strtolower(\array_shift($parameters));
		$uri = '/';

		if (isset($this->routes['routeto'][$search])) {
			$uri .= $this->routes['routeto'][$search];

			if (preg_match_all('/\(([^)]+)\)/', $uri, $matches)) {
				$matchCount = count($matches[0]);
				$parameterCount = count($parameters);

				/* make sure we got enough parameters (more is better than not enough) */
				if ($matchCount > $parameterCount) {
					throw new ParameterException('expected ' . $matchCount . ' received: ' . $parameterCount);
				}

				foreach ($matches[0] as $idx => $matchString) {
					$string = (isset($parameters[$idx])) ? $parameters[$idx] : '';
					$uri = preg_replace('/' . preg_quote($matchString, '/') . '/', $string, $uri, 1);
				}
			}
		} else {
			throw new ArrayKeyNotFoundException($search);
		}

		return $uri;
	}
} /* end class */
