<?php

namespace Weebel\Container;

use Weebel\Contracts\Caller;
use Weebel\Contracts\Container as ContainerInterface;

class ServiceContainer implements ContainerInterface, Caller
{
    private static ?ServiceContainer $instance = null;
    protected array $registered = [];
    protected array $resolved = [];
    protected array $arguments = [];
    protected array $aliases = [];

    protected function __construct()
    {
    }

    /**
     * @throws ServiceNotFound
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->aliases)) {
            return $this->get($this->aliases[$id]);
        }

        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        $resolved = $this->resolve($id);
        $this->resolved[$id] = $resolved;
        return $resolved;
    }

    /**
     * @throws ContainerException
     * @throws ServiceNotFound
     */
    public function make(string $class, array $arguments): mixed
    {
        try {
            return new $class(...$this->inputParameters(
                (new \ReflectionClass($class))->getConstructor()?->getParameters(),
                $arguments
            ));
        } catch (\ReflectionException $e) {
            throw new ContainerException($e->getMessage());
        }
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->registered);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->registered;
    }

    public function addArgument(string $tag, string $key, mixed $value): static
    {
        $this->arguments[$tag][$key] = $value;

        return $this;
    }


    public function addArguments(string $tag, array $arguments): static
    {
        $this->arguments[$tag] = array_merge($this->arguments[$tag], $arguments);

        return $this;
    }

    public function loadServices(array $services): static
    {
        $this->registered = array_merge($this->registered, $services);

        return $this;
    }

    /**
     * @throws ServiceNotFound
     * @throws ContainerException
     */
    public function call(string $class, string $method, array $arguments = []): mixed
    {
        try {
            $parameters = (new \ReflectionClass($class))->getMethod($method)?->getParameters();
        } catch (\ReflectionException $e) {
            throw new ContainerException($e->getMessage());
        }

        $entity = $this->get($class);

        if (!method_exists($entity, $method)) {
            throw new ContainerException(sprintf('Class %s does not have method %s.', $class, $method));
        }

        return $entity->{$method}(...$this->inputParameters($parameters, $arguments));
    }

    public function set(string $id, $value): static
    {
        $this->registered[$id] = $value;

        return $this;
    }

    public function flush(): static
    {
        $this->registered = [];
        $this->resolved = [];
        $this->arguments = [];

        return $this;
    }

    public static function getInstance(): static
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    private function resolveParameter(\ReflectionParameter $parameter, array $arguments = []): mixed
    {
        if ($arguments && array_key_exists($parameter->getName(), $arguments)) {
            return $this->resolveFromArguments($arguments, $parameter->getName());
        }

        if ($this->has($parameter->getName())) {
            return $this->get($parameter->getName());
        }

        $class = $parameter->getType()?->getName();
        if ($class && !in_array($class, ["bool", "string", "array", "mixed", "int", "object", "callable"])) {
            return $this->get($class);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \RuntimeException("Unable to resolve the parameter : " . $parameter->getName());
    }


    /**
     * @throws ServiceNotFound
     */
    private function inputParameters(?array $parameters, array $arguments = []): array
    {
        if (!$parameters) {
            return [];
        }
        $inputParameters = [];

        foreach ($parameters as $parameter) {
            $inputParameters[] = $this->resolveParameter($parameter, $arguments);
        }
        return $inputParameters;
    }


    /**
     * @throws ServiceNotFound
     * @throws \ReflectionException
     */
    private function resolveFromCallable(callable $callable, array $arguments = [])
    {
        return $callable(...$this->inputParameters((new \ReflectionFunction($callable))->getParameters(), $arguments));
    }


    /**
     * @throws ServiceNotFound
     * @throws \ReflectionException
     */
    private function resolveFromRegistered(string $id, array $arguments = []): mixed
    {
        $registered = $this->registered[$id];

        if (is_callable($registered)) {
            return $this->resolveFromCallable($registered, $arguments);
        }

        if (!is_string($registered)) {
            return $registered;
        }

        return $this->resolve($registered, $arguments);
    }

    /**
     * @throws ServiceNotFound
     */
    private function resolve(string $id, array $arguments = []): mixed
    {
        if (array_key_exists($id, $this->arguments)) {
            $arguments = array_merge($this->arguments[$id], $arguments);
        }

        try {
            if (array_key_exists($id, $this->registered)) {
                return $this->resolveFromRegistered($id, $arguments);
            }

            return $this->make($id, $arguments);
        } catch (\Throwable $e) {
            throw new ServiceNotFound("Unable to resolve the id of : $id. " . $e->getMessage());
        }
    }

    public function alias(string $id, string $value): static
    {
        $this->aliases[$id] = $value;

        return $this;
    }

    /**
     * @throws ServiceNotFound
     */
    private function resolveFromArguments($arguments, $parameter): mixed
    {
        $argument = $arguments[$parameter];
        if (is_string($argument) && $argument[0] === '@') {
            return $this->get(substr($argument, 1));
        }

        if (is_callable($argument)) {
            return $this->resolveFromCallable($argument);
        }

        return $argument;
    }
}
