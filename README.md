# Enstart Extension: Croute

> This extension is still in development and should not be used in production until there's at least one tagged release.

Create routes using doc block annotations in your controllers doc blocks. You will still be able to use the default way to create routes.

### Dependencies:

* `enstart/core` version 0.2+
* PHP 7.0+

### Install

    composer require enstart/croute

### Config:

    // Settings
    'croute' => [
        // Enable the parser
        'enabled'     => true,

        // Use the cached file, if it exists
        'use_cache'   => false,

        // Path to the cache
        'cache'       => '/path/to/cache/folder',

        'controllers' => [
            // Namespace => path
            'App\Controllers\Api' => __DIR__ . '/app/Controllers/Api',
        ],
    ],

    // Register the service provider
    'providers' => [
        ...
        'Enstart\Ext\Croute\ServiceProvider',
    ],


### Access the extension

    // Get a copy of the instance
    $croute = $app->container->make('Enstart\Ext\Croute\Parser');

    // or through the alias:
    $app->croute

    // or through dependency injection (if you type hint it in your constructor)
    use Enstart\Ext\Croute\Parser;


### Annotation

Below is a simple method annotation

    class TestController
    {
        /**
         * Get list of items
         *
         * @route GET /items
         *
         * @return json
         */
        public function list()
        {
            return $this->makeJsonEntity(true, ['list of items']);
        }
    }


This will register the route `/items` with the GET method.


#### Route prefix for the whole class


    /**
     * @routePrefix /test
     */
    class TestController
    {
        /**
         * Get list of items
         *
         * @route GET /items
         *
         * @return json
         */
        public function list()
        {
            return $this->makeJsonEntity(true, ['list of items']);
        }
    }

The above will register the route `/test/items`. All method routes in the above class will have the `/test`-prefix.

#### Optional route settings

Just like the normal router, you can add things like filters and named routes:

    /**
     * Get list of items
     *
     * @route GET /items
     * @routeName list-items
     * @before filter1|filter2|...
     * @after  filter1|filter2|...
     *
     * @return json
     */
    public function list()
    {
        return $this->makeJsonEntity(true, ['list of items']);
    }

The annotations `@before` and `@after` can also be used for the class annotations.

### Caching

...more info will come.
