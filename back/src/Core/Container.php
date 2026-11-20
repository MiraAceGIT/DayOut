<?php
declare(strict_types=1);

namespace App\Core;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Conteneur d'injection minimal : une instance par classe et par conteneur (donc par requete).
 * Sans fabrique declaree via set(), une classe est construite en resolvant chaque parametre type
 * classe de son constructeur ; un parametre scalaire doit avoir une valeur par defaut.
 */
final class Container
{
    /** @var array<string, Closure(self): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> classes en cours de construction (detection des cycles) */
    private array $building = [];

    /** @param Closure(self): object $factory */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T */
            return $this->instances[$id];
        }
        $object = isset($this->factories[$id]) ? ($this->factories[$id])($this) : $this->build($id);
        if (!$object instanceof $id) {
            throw new LogicException("Factory for $id returned " . $object::class);
        }
        return $this->instances[$id] = $object;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new LogicException("Cannot resolve $class: no factory and not a class");
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new LogicException("Cannot resolve $class: not instantiable (declare a factory with set())");
        }
        if (isset($this->building[$class])) {
            throw new LogicException('Circular dependency: ' . implode(' -> ', array_keys($this->building)) . " -> $class");
        }

        $this->building[$class] = true;
        try {
            $args = [];
            foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    /** @var class-string $dependency */
                    $dependency = $type->getName();
                    $args[] = $this->get($dependency);
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else {
                    throw new LogicException("Cannot resolve \${$parameter->getName()} of $class");
                }
            }
            return $reflection->newInstanceArgs($args);
        } finally {
            unset($this->building[$class]);
        }
    }
}
