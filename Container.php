<?php

/**
 * This file is part of the Bulk CMS.
 *
 * (c) Jerson Carin <jersoncarin25@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Bulk\Components\Container;

use Psr\Container\ContainerInterface;
use Bulk\Components\Container\Exception\NotFoundException;
use Bulk\Components\Extendable\Trait\Extendable as ExtendableTrait;

class Container implements ContainerInterface {

    use ExtendableTrait;

    /**
     * Registered services
     * 
     * @var array
     */
    public array $services = [];

    /**
     * Resolved registered once services
     * 
     * @var array
     */
    public array $resolveOnce = [];

    /**
     * Resolver
     * 
     * @var Resolver
     */
    protected Resolver $resolver;

    /**
     * Constructor
     */
    public function __construct() 
    {
        $this->resolver = new Resolver();
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get(string $id): mixed
    {
        if(!array_key_exists($id,$this->services)) {
            throw new NotFoundException("No entry was found for '$id' identifier.");
        }

        return $this->resolveDependencies($id,[]);
    }

     /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @param mixed ...$parameters
     *
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function make(string $id,mixed ...$parameters): mixed
    {
        if(!array_key_exists($id,$this->services)) {
            $this->set($id,$id);
        }

        return $this->resolveDependencies($id,$parameters);
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        if(array_key_exists($id,$this->services)) {
            return true;
        }

        return false;
    }

    /**
     * Put into service an instantiated class
     * 
     * @param string $id Identifier of the entry to look for.
     * @param mixed $concrete
     * 
     * @return self
     */
    public function instance(string $id,mixed $concrete): self
    {
        if(is_object($concrete) && get_class($concrete) !== false) {
            $this->services[$id] = [
                'once' => false,
                'concrete' => $concrete,
                'instance' => true
            ];
        }

        return $this;
    }

    /**
     * Put into service an class/callable function
     * and call once
     * 
     * @param string $id Identifier of the entry to look for.
     * @param mixed $concrete
     * 
     * @return self
     */
    public function once(string $id,mixed $concrete): self
    {
        $this->services[$id] = [
            'once' => true,
            'concrete' => $concrete
        ];

        return $this;
    }

     /**
     * Put into service an class/callable function
     * 
     * @param string $id Identifier of the entry to look for.
     * @param mixed $concrete
     * 
     * @return self
     */
    public function set(string $id,mixed $concrete = NULL): self
    {
        $this->services[$id] = [
            'once' => false,
            'concrete' => $concrete
        ];

        return $this;
    }

    /**
     * Remove service from the container by key
     * 
     * @param string $id Identifier of the entry to remove
     * 
     * @return self
     */
    public function remove(string $id): self
    {
        if(array_key_exists($id,$this->services)) {
            unset($this->services[$id]);
        }

        return $this;
    }

    /**
     * Call the callback/closure/methods and invokes
     * 
     * You can use function/closure or methods in a class
     * If your calling method in a class you can use
     * `ClassName@methodName` like `User@show or
     * using array [User::class,'show'] or if you in the class
     * you can use $this [$this,'show']
     * Note: make sure the method in the class is in public otherwise
     * It won't work expectedly
     * 
     * @param mixed $concrete
     * @param mixed $params
     * 
     * @return mixed
     */
    public function call(mixed $concrete,mixed ...$params): mixed
    {
        // Set Resolver Services
        $this->resolver->setContainer($this);

        // Split two parts of class and methods
        // Like `User@show` format
        if(is_string($concrete) && str_contains($concrete,'@')) {
            $parts = explode('@',$concrete);

            if(count($parts) === 2) {
                $concrete = $parts;
            }
        }

        $callback = is_array($concrete)
            ? (count($concrete) === 2 ? $concrete : [])
            : $concrete;

        return $this->resolver->resolveCallbacks($callback,$params);
    }

    /**
     * Resolve the concretes dependencies
     * 
     * @param string $id Identifier of the entry to look for.
     * @param array $parameters
     * 
     * @return mixed
     */
    protected function resolveDependencies(string $id,array $parameters): mixed
    {
        $service = $this->services[$id];

        // Set Resolver Services
        $this->resolver->setContainer($this);

        // If callable is called and we can handle it
        if(($resolve = $this->resolver->resolveCallable($id,$service,$parameters)[1]) !== false) {

            // If the service is call once
            // We store it on onces list and once it called,it will never called
            // again and instead we use the first called from the onces list
            if($service['once'] && !isset($this->resolveOnce[$id])) {
                $this->resolveOnce[$id] = $resolve;
            }

            return $resolve;
        }

        if(($resolve = $this->resolver->resolveClass($id,$service,$parameters)[1]) !== false) {

            // If the service is call once
            // We store it on onces list and once it called,it will never called
            // again and instead we use the first called from the onces list
            if($service['once'] && !isset($this->resolveOnce[$id])) {
                $this->resolveOnce[$id] = $resolve;
            }

            return $resolve;
        }

        // If not class nor callable/closure
        if($service['once'] && !isset($this->resolveOnce[$id])) {
            return $this->resolveOnce[$id] = $service['concrete'];
        }

        return isset($this->resolveOnce[$id])
            ? $this->resolveOnce[$id]
            : $service['concrete'];
    }

    /**
     * Get the resolver
     * 
     * @return \Resolver
     */
    public function getResolver(): Resolver
    {
        return $this->resolver;
    }
}