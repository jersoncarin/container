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

use Closure;
use ReflectionClass;
use ReflectionFunction;
use Bulk\Components\Container\Exception\ContainerException;
use ReflectionMethod;

class Resolver {

    /**
     * Services
     * 
     * @var array
     */
    protected Container $container;

    /**
     * Set Services Container
     * 
     * @param array $services
     * 
     * @return void
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Collapse the array
     * 
     * @param array $input
     * 
     * @return array $output
     */
    private function collapse(array $input): array
    {
        // Collapse the params if collapsable
        $items = [];
        array_walk_recursive($input,function($item) use(&$items) { 
            $items[] = $item; 
        });

        return $items;
    }

    /**
     * Resolve Callable/Closures
     * 
     * @param string $id
     * @param array $service
     * 
     * @return array
     */
    public function resolveCallable(string $id,array $service,array $params): array
    {
        [$once,$concrete] = array_values($service);

        if(!is_callable($concrete) || !$concrete instanceof Closure) {
            return [$once,false];
        }

        // If already cache on onces list we could easy
        // return directly and call it
        if(isset($this->container->resolveOnce[$id])) {
            return [$once,$this->container->resolveOnce[$id]];
        }
        
        // Collapse the params if collapsible
        $params = $this->collapse($params);

        // Create an reflection
        $concrete = new ReflectionFunction($concrete);
        $parameters = $concrete->getParameters();
      
        // Get resolve dependencies
        $final_parameters = $this->resolveReflectorDependencies(
            $parameters,
            $params
        );

        // Filter it!
        $final_parameters = array_filter($final_parameters);

        return [$once,$concrete->invokeArgs($final_parameters)];
    }

    /**
     * Resolve Classes
     * 
     * @param string $id
     * @param array $service
     * @param array $params
     * 
     * @return array
     */
    public function resolveClass(string $id,array $service,array $params): array
    {
        [$once,$concrete] = array_values($service);

        if(!is_object($concrete) && (is_string($concrete) && !class_exists($concrete))) {
            return [$once,false];
        }

        // If already cache on onces list we could easy
        // return directly and call it
        if(isset($this->container->resolveOnce[$id])) {
            return [$once,$this->container->resolveOnce[$id]];
        }

        // If this concrete an instance already
        // we can call it directly without Di
        if(isset($service['instance']) && $service['instance'] && is_object($concrete)) {
            return [$once,$concrete];
        }

        // Collapse the params if collapsible
        $params = $this->collapse($params);

        // Create an reflection class
        $concrete = new ReflectionClass($concrete);

        // check if class is instantiable
		if (!$concrete->isInstantiable()) {
			throw new ContainerException("Class {$concrete} is not instantiable");
		}

        $constructor = $concrete->getConstructor();

        // Constructor is not defined in the class
        // so we can directly create new instance
        if($constructor === null) {
            return [$once,$concrete->newInstance()];
        }

        $parameters = $constructor->getParameters();

        // Get resolve dependencies
        $final_parameters = $this->resolveReflectorDependencies(
            $parameters,
            $params
        );

        // Filter it
        $final_parameters = array_filter($final_parameters);

        return [$once,$concrete->newInstanceArgs($final_parameters)];
    }

    /**
     * Resolve Reflector Dependencies
     * 
     * @param array $parameters
     * @param mixed $concrete
     * @param array $params
     * 
     * @return array
     */
    protected function resolveReflectorDependencies(array $parameters,array $params): array
    {
        $final_parameters = [];

        $param_counter = 0;
        foreach($parameters as $parameter) {
            $type = (string) $parameter->getType();

            if(class_exists($type) || interface_exists($type)) {
                $typeClass = new ReflectionClass($type);
                
                if(!$typeClass->isInstantiable()) {
                    throw new ContainerException("Class {$type} is not instantiable");
                }

                // Filters service with objects only
                // and get the class name 
                $objects = array_map(function($service) {
                    $service['concrete'] = get_class($service['concrete']) ?? '';
                    return $service;
                },array_filter($this->container->services,function($service) {
                    return is_object($service['concrete']) 
                        && class_exists(get_class($service['concrete']))
                        && !$service['concrete'] instanceof Closure;
                }));

                // Object is empty, make new instance directly
                if(empty($objects)) {
                    $final_parameters[] = $typeClass->newInstance();
                } 
                // else if object is not empty we will try to
                // find the object and match it from the typehinted class
                // if we find the we will use that typehint class as a parameter value
                else {

                    foreach($objects as $k => $object) {

                        // Compare if concrete name and typehinted class name
                        // is equals and same with data type
                        // if it's equals then we could push it on final_parameters
                        if($typeClass->getName() === $object['concrete']) {
                            $final_parameters[] = $this->container->services[$k]['concrete'] ?? '';
                        }
                    }

                }

            } elseif(!empty($params)) {

                $final_parameters[] = $params[$param_counter] ?? '';
                // map the stacks params contains all map classes
                // if parameters is a string and a class
                // we could try to create new instance
                $final_parameters = array_map(function($service) {

                    // if this is a string and a class or interface we could create new instance
                    // with this service
                    if(is_string($service) && (class_exists($service) || interface_exists($service))) {

                        $typeClass = new ReflectionClass($service);
                
                        if(!$typeClass->isInstantiable()) {
                            throw new ContainerException("Class {$service} is not instantiable");
                        }
        
                        // Filters service with objects only
                        // and get the class name 
                        $objects = array_map(function($service) {
                            $service['concrete'] = get_class($service['concrete']) ?? '';
                            return $service;
                        },array_filter($this->container->services,function($service) {
                            return is_object($service['concrete']) 
                                && class_exists(get_class($service['concrete']))
                                && !$service['concrete'] instanceof Closure;
                        }));
        
                        // Object is empty, make new instance directly
                        if(empty($objects)) {
                            return $typeClass->newInstance();
                        } 
                        // else if object is not empty we will try to
                        // find the object and match it from the typehinted class
                        // if we find the we will use that typehint class as a parameter value
                        else {
        
                            foreach($objects as $k => $object) {
        
                                // Compare if concrete name and typehinted class name
                                // is equals and same with data type
                                // if it's equals then we could push it on final_parameters
                                if($typeClass->getName() === $object['concrete']) {
                                    return $this->container->services[$k]['concrete'] ?? null;
                                }
                            }
        
                        }
        
                    }

                    return $service;

                },$final_parameters);

                $param_counter++;
            }

            // Check for optional parameters
            if($parameter->isDefaultValueAvailable()) {
                if($parameter->isDefaultValueConstant()) {
                    $final_parameters[] = $parameter->getDefaultValueConstantName();
                } else {
                    $final_parameters[] = $parameter->getDefaultValue();
                }
            }
        }

        return $final_parameters;
    }

    /**
     * Resolve callbacks like closure/callable function/methods
     * 
     * @param mixed $callback
     * @param array $params
     * 
     * @return mixed
     */
    public function resolveCallbacks(mixed $callback,array $params): mixed
    { 
        // Check if callback is not
        // callable then return it with false
        if(is_array($callback) && count($callback) === 2) {
            if(!is_object($callback[0]) && is_string($callback[0])) {
                
                foreach($this->container->services as $key => $service) {

                    // If we find this on service container
                    // we can directly assign it
                    if(get_class($this->container->get($key)) === $callback[0]) {

                        // Get instance from the container
                        $callback[0] = $this->container->get($key);
                        
                    } else {
                        // Create new instance in the class if not exist
                        // on the service container
                        $instance = $this->container->make($callback[0]);
                        
                        // Remove after resolve
                        $this->container->remove($callback[0]);

                        // Assign it to the callback
                        $callback[0] = $instance;

                        break;
                    }
                }
            }
        }

        // Collapse the params if collapsible
        $params = $this->collapse($params);

        // Throw an ContainerException
        // if callback is not callable
        if(!is_callable($callback)) {
            $trace = is_array($callback) && count($callback) === 2 
                ? (is_string($callback[1]) ? $callback[1] : 'Method')
                : 'Closure or Function';

            throw new ContainerException("'$trace' is not callable");
        }

        // Do checking for method and function callables
        if((is_array($callback) && count($callback) === 2)) {
            $reflector = new ReflectionMethod($callback[0],$callback[1]); 
        } else {
            $reflector = new ReflectionFunction($callback);
        }

        $parameters = $reflector->getParameters();

        // Resolved Dependencies
        $resolvedDependencies = $this->resolveReflectorDependencies(
            $parameters,
            $params
        );

        // Filter it!
        $resolvedDependencies = array_filter($resolvedDependencies);

        // Invoke the methods/function with parameters (if any)
        if($reflector instanceof ReflectionMethod) {
            return $reflector->invokeArgs($callback[0],$resolvedDependencies);
        } elseif($reflector instanceof ReflectionFunction) {
            return $reflector->invokeArgs($resolvedDependencies);
        }

        return false;
    }
}