<?php

namespace Minneola\TestFoo\Core;

use Minneola\TestFoo\Core\Arcadia\Loader;
use Minneola\TestFoo\Crash\Diamon;
use Minneola\TestFoo\Support\Facade;

/**
 * Class Application
 * @package Minneola\TestFoo\Core
 * @author Tobias Maxham
 */
class Application implements \ArrayAccess
{

	public static $app;
	public  static $smiles = ['GET' => [], 'POST' => []];

	private $instances = [];

	private $rootPath;

	protected $aliases = [
		'app' => 'Minneola\\TestFoo\\Core\\Application',
		'cain' => 'Minneola\\TestFoo\\Mangold\\CainManager',
		'smile' => 'Minneola\\TestFoo\\Macaroni\\SmileFactory',
	];

	public function __construct($path = NULL)
	{
		//var_dump($path, self::$app);
		if(isset(self::$app)){
			$this->rootPath = self::app()->rootPath();
		} else {

			$this->rootPath = $path;
		}
		self::$app = &$this;
		return self::$app;
	}

	public function rootPath()
	{
		return $this->rootPath;
	}

	public function viewPath()
	{
		return __DIR__ . '/../../../../../views/';
	}

	public static function app()
	{
		return self::$app;
	}

	public static function smiles()
	{
		$app = self::app();
		return $app::$smiles;
	}

	public static function setSmile($method, $path, $attributes = NULL)
	{
		self::$smiles[$method][$path] = [$path, $attributes];
	}

	public function boot()
	{
		$this->initiate();
		$this->loadSmiles();
		return $this;
	}

	public function run()
	{
		$data = new Diamon();
		$url = $data->request_uri;
		$method = $data->request_method;

		if(($ctr = $this->checkExistingSmiles($url, $method)) !== FALSE) return $ctr;
		if(($ctr = $this->checkExistingSmiles(substr($url,1), $method))!== FALSE) return $ctr;
		return NULL;
	}

	private function checkExistingSmiles($url, $method)
	{
		if(!array_key_exists($url, \App::smiles()[$method])) return FALSE;
		if(\App::smiles()[$method][$url][1] instanceof \Closure)
			return call_user_func(\App::smiles()[$method][$url][1]);

		$st = explode('@', \App::smiles()[$method][$url][1]);
		if(count($st) != 2) throw new \Exception('Wrong controller declaration.');

		$realController = 'App\\Controller\\'.$st[0];

		$init = new $realController(\App::getApp());
		$init->setControllerAction($st[1]);
		return $init;
	}

	private function initiate()
	{
		Facade::clearAll();
		Facade::setApp($this);
		Loader::getInstance($this->getAliases())->register();
	}

	/**
	 * @return \Minneola\TestFoo\Core\Application
	 */
	public static function getApp()
	{
		return self::$app;
	}

	private function loadSmiles()
	{
		$file = $this->rootPath . '/app/smiles.php';
		if(!file_exists($file)) throw new \Exception("The File $file was not found!");
		require_once $file;
	}

	/**
	 * @return array $aliases
	 */
	public function getAliases()
	{
		return require $this->rootPath . '/config/setup.php';
	}

	public function alias()
	{
		foreach ($this->getAliases() as $alias => $class) {
			class_alias($class, $alias);
		}
	}

	public function offsetExists($key)
	{
		return isset($this->aliases[$key]);
	}

	public function offsetGet($key)
	{
		return $this->make($key);
	}

	public function make($abstract)
	{
		$abstract = $this->getAlias($abstract);
		return new $abstract;
	}

	protected function getAlias($abstract)
	{
		return isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
	}

	public function offsetSet($key, $value)
	{
		if (!$value instanceof \Closure) {
			$value = function () use ($value) {
				return $value;
			};
		}
		$this->instances[$key] = $value;
	}

	public function offsetUnset($key)
	{
		unset($this->instances[$key]);
	}

} 