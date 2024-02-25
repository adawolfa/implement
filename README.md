Implementation generator for interfaces and abstract classes.

Generates a class extending or implementing given type and forwards all calls to its methods to a given call handler.

### Installation

~~~bash
composer require adawolfa/implement
~~~

### Usage

~~~php
interface MyService
{

	function foo();

}

$handler = new class implements Adawolfa\Implement\Handler {

	public function handle(Adawolfa\Implement\Call $call) : mixed
	{
		var_dump($call->method->name); // foo
		return 'bar';
	}

};

$generator      = new Adawolfa\Implement\Generator;
$implementation = $generator->generate(MyService::class);
$service        = $implementation->construct($handler);

var_dump($service->foo()); // bar
~~~

### Supports

- non-static methods
- parameters passed by reference (write into call arguments, e.g. `$call->arguments['param'] = 123;`)
- returning by reference
- abstract methods from traits
- multi-level inheritance
- intersection & union types
- attributes (copied from declaration to implementation)
- documentation comments (ditto)
- strict-types
- memory cache (for development) & file cache (for production, opcache optimized)