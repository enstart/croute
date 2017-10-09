<?php namespace Enstart\Ext\Croute;

use Enstart\Router\RouterInterface;

class Parser
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * @var boolean
     */
    protected $enabled = false;

    /**
     * Cache path
     * @var string
     */
    protected $cachePath;

    /**
     * Use Cache
     * @var boolean
     */
    protected $useCache = true;

    /**
     * Controllers
     * @var array
     */
    protected $controllers = [];


    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }


    /**
     * Enable/disable the parser or get the current status
     *
     * @param  boolean $status
     * @return bool|$this
     */
    public function enabled($status = null)
    {
        if (is_null($status)) {
            return $this->enabled;
        }

        $this->enabled = (bool) $status;
        return $this;
    }


    /**
     * Set cache path
     *
     * @param  string $path
     * @return null|string|this
     */
    public function cachePath($path)
    {
        if (is_null($path)) {
            return $this->cachePath;
        }

        $this->cachePath = realpath($path);
        return $this;
    }


    /**
     * Get/set use cache
     *
     * @param  boolean $use
     * @return boolean|this
     */
    public function useCache($use = null)
    {
        if (is_null($use)) {
            return $this->useCache;
        }

        $this->useCache = (bool) $use;
        return $this;
    }


    /**
     * Register controller paths
     *
     * @param array $controllers
     */
    public function setControllers(array $controllers)
    {
        $this->controllers = $controllers;
        return $this;
    }


    /**
     * Register routes from controllers
     * @return [type] [description]
     */
    public function registerControllerRoutes()
    {
        if ($this->enabled) {
            $routes = $this->getControllerRoutes();
            $this->registerRoutes($routes);
        }

        return $this;
    }


    /**
     * Generate route cache from controllers
     *
     * @return bool
     */
    public function generateControllerRoutes()
    {
        if ($this->enabled && $this->cachePath) {
            $routes = $this->getControllerRoutes(true);
            $file   = $this->cachePath . '/routes.json';
            return @file_put_contents($file, json_encode($routes)) > 0;
        }

        return false;
    }


    /**
     * Get all routes from all registered controllers
     *
     * @param  boolean $reread
     * @return array
     */
    protected function getControllerRoutes($reread = false)
    {
        if ($this->useCache && !$reread && $this->cachePath) {
            $file   = $this->cachePath . '/routes.json';
            $routes = is_file($file) ? @json_decode(file_get_contents($file), true) : false;
            return $routes ?: [];
        }

        $routes = [];
        foreach ($this->controllers as $ns => $path) {
            $routes = array_replace_recursive($routes, $this->parseControllers($ns, $path));
        }

        return $routes;
    }


    /**
     * Iterate the controllers to get the routes
     *
     * @param  string $ns
     * @param  string $path
     * @return array
     */
    protected function parseControllers($ns, $path)
    {
        $routes = [];
        foreach (glob($path . '/*.php') as $f) {
            $file   = new \SplFileInfo($f);
            $class  = $ns . '\\' . $file->getBasename('.php');
            if (!class_exists($class)) {
                continue;
            }

            $result = $this->getClassRoutes($class);
            $routes = array_replace_recursive($routes, $result);
        }

        return $routes;
    }


    /**
     * Get all routes from a class
     *
     * @param  string $class
     */
    protected function getClassRoutes($class)
    {
        $classReflector = new \ReflectionClass($class);
        $routes         = [];
        $group          = $this->getClassAnnotations($classReflector->getDocComment());

        $prefix = $group['prefix'] ?? '';
        $before = $group['before'] ?? '';
        $after  = $group['after'] ?? '';

        foreach ($classReflector->getMethods() as $methodReflector) {
            $annotations = $this->getMethodAnnotations($methodReflector->getDocComment());

            if (!$annotations) {
                continue;
            }

            $filters = [];
            if ($annotations['before'] || $before) {
                $filters['before'] = ($before  ?? '') . '|' . ($annotations['before'] ?? '');
                $filters['before'] = trim($filters['before'], '|');
            }

            if ($annotations['after'] || $after) {
                $filters['after'] = ($after  ?? '') . '|' . ($annotations['after'] ?? '');
                $filters['after'] = trim($filters['after'], '|');
            }

            if ($annotations['name']) {
                $filters['name'] = $annotations['name'];
            }

            foreach ($annotations['routes'] as $route) {
                $route['route'] = '/' . trim($prefix . '/' . trim($route['route'], '/'), '/');
                $cb  = $methodReflector->class . '@' . $methodReflector->getName();

                $routes[] = [
                    'method'   => $route['method'],
                    'pattern'  => $route['route'],
                    'callback' => $cb,
                    'filters'  => $filters,
                ];
            }
        }

        return $routes;
    }


    /**
     * Get all relevant doc comment annotations
     *
     * @param  string $docComment
     * @return array
     */
    protected function getClassAnnotations($docComment)
    {
        $annotations = [
            'prefix' => '',
            'before' => [],
            'after'  => [],
        ];

        preg_match_all('/\@(\w+)\s+([^\s]+)\s+([^\s]+)?/i', $docComment, $match);

        if (empty($match[1])) {
            return [];
        }

        foreach ($match[1] as $i => $value) {
            if ('before' == $value && !empty($match[2][$i])) {
                $annotations['before'][] = $match[2][$i];
            } elseif ('after' == $value && !empty($match[2][$i])) {
                $annotations['after'][] = $match[2][$i];
            } elseif ('routePrefix' == $value && !empty($match[2][$i]) && $match[2][$i] != '/') {
                $annotations['prefix'] = '/' . trim($match[2][$i], '/');
            }
        }

        return [
            'prefix' => $annotations['prefix'],
            'before' => implode('|', $annotations['before']),
            'after'  => implode('|', $annotations['after']),
        ];
    }


    /**
     * Get all relevant doc comment annotations
     *
     * @param  string $docComment
     * @return array
     */
    protected function getMethodAnnotations($docComment)
    {
        $annotations = [
            'routes' => [],
            'before' => [],
            'after'  => [],
            'name'   => null,
        ];

        preg_match_all('/\@(\w+)\s+([^\s]+)\s+([^\s]+)?/i', $docComment, $match);

        if (empty($match[1])) {
            return [];
        }

        foreach ($match[1] as $i => $value) {
            if ('route' == $value && !empty($match[2][$i]) && !empty($match[3][$i])) {
                $annotations['routes'][] = [
                    'route'  => $match[3][$i],
                    'method' => strtoupper($match[2][$i]),
                ];
            } elseif ('before' == $value && !empty($match[2][$i])) {
                $annotations['before'][] = $match[2][$i];
            } elseif ('after' == $value && !empty($match[2][$i])) {
                $annotations['after'][] = $match[2][$i];
            } elseif ('routeName' == $value && !empty($match[2][$i])) {
                $annotations['name'][] = $match[2][$i];
            }
        }

        return [
            'routes' => $annotations['routes'],
            'before' => implode('|', $annotations['before']),
            'after'  => implode('|', $annotations['after']),
            'name'   => $annotations['name'],
        ];
    }


    /**
     * Register routes
     *
     * @return $this
     */
    protected function registerRoutes($routes)
    {
        foreach ($routes as $route) {
            $this->router->add($route['method'], $route['pattern'], $route['callback'], $route['filters']);
        }

        return $this;
    }
}
