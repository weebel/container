<?php

namespace Weebill\Container;



class Container
{
    private static $instance;
    protected $registered = [];
    protected $arguments = [];

    protected function __construct()
    {
    }

    public function addArgument(string $class, string $key, string $value)
    {
        $this->arguments[$class][$key] = $value;
    }

    public function get(string $id)
    {
        if (array_key_exists($id, $this->registered)) {
            return $this->resolveFromRegistered($id);
        }

        try {
            return $this->make($id);
        } catch (\Throwable $e) {
            throw new EntityNotFound("Unable to resolve the id of : $id. " . $e->getMessage());
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


    /**
     * @throws \ReflectionException
     */
    public function make(string $class)
    {
        $this->set($class, $entity = new $class(...$this->inputParameters(
            (new \ReflectionClass($class))->getConstructor()?->getParameters(), $class
        )));

        return $entity;
    }


    private function resolveParameter(\ReflectionParameter $parameter, $class = null)
    {
        if ($class && isset($this->arguments[$class][$parameter->getName()])){
            return $this->arguments[$class][$parameter->getName()];
        }
        if ($this->has($parameter->getName())) {
            return $this->get($parameter->getName());
        }
        $class = $parameter->getType()?->getName();
        if ($class && !in_array($class, ["bool", "string", "array", "mixed", "int", "object", "callable"])) {
            try {
                return $this->get($class);
            } catch (EntityNotFound $exception) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                throw $exception;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \Exception("Unable to resolve the parameter : " . $parameter->getName());
    }

    public function loadServices(array $services)
    {
        $this->registered = $services;
    }

    public function call(string $class, string $method): mixed
    {
        $parameters = (new \ReflectionClass($class))->getMethod($method)?->getParameters();
        $entity = $this->get($class);

        if (!method_exists($entity, $method)) {
            throw new \RuntimeException(sprintf('Class %s does not have method %s.', $class, $method));
        }

        return $entity->{$method}(...$this->inputParameters($parameters));
    }


    private function inputParameters(?array $parameters, string $class = null): array
    {
        if (!$parameters) {
            return [];
        }
        $inputParameters = [];

        foreach ($parameters as $parameter) {
            $inputParameters[] = $this->resolveParameter($parameter, $class);
        }
        return $inputParameters;
    }

    public function set(string $id, $value): void
    {
        $this->registered[$id] = $value;
    }

    /**
     * @throws \ReflectionException
     */
    private function resolveFromCallable(callable $callable, $id)
    {
        $this->set($id, $entity = $callable(...$this->inputParameters((new \ReflectionFunction($callable))->getParameters(), $id)));
        return $entity;
    }

    /**
     * Get the globally available instance of the container.
     */
    public static function getInstance(): static
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param string $id
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    private function resolveFromRegistered(string $id): mixed
    {
        if (is_callable($this->registered[$id])) {
            return $this->resolveFromCallable($this->registered[$id], $id);
        }

        if (!is_string($this->registered[$id])) {
            return $this->registered[$id];
        }

        return $this->get($this->registered[$id]);
    }
}
