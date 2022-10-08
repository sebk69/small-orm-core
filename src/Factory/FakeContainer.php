<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Factory;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class FakeContainer
 * @package Sebk\SmallOrmCore\Factory
 */
class FakeContainer implements ContainerInterface
{
    const SMALL_ORM_BUNDLE_SERVICES = __DIR__ . "/../Resources/config/services.yml";

    protected $isolated = false;
    protected $servicesDefinition = [];
    /** @var array $isolatedService */
    protected $isolatedService = [];
    /** @var array $isolatedParameters */
    protected $isolatedParameters = [];

    /**
     * FakeContainer constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        foreach ($container->getParameterBag()->all() as $id => $value) {
            $this->setParameter($id, $value);
        }

        $this->addServicesYaml(self::SMALL_ORM_BUNDLE_SERVICES);
    }

    /**
     * Isolate fake container
     */
    public function isolate()
    {
        $this->isolated = true;
    }

    /**
     * Add a service to use it in isolated environment
     * @param $id
     */
    public function addServiceForIsolation($id, $class, $parameters = [])
    {
        $this->servicesDefinition[$id] = ["class" => $class, "params" => $parameters];
    }

    /**
     * Add a parameter tu use it in isolated environment
     * @param $name
     */
    public function addIsolatedParameter($name)
    {
        $this->isolatedParameters[$name] = $this->container->getParameter($name);
    }

    /**
     * Set a service in container and add it for isolated environment
     * @param string $id
     * @param object $service
     * @throws \Exception
     */
    public function set($id, $service)
    {
        if ($id == self::DAO_FACTORY_SERVICE) {
            throw new \Exception("Can't override dao factory in fake container");
        }

        if (!$this->isolated) {
            $this->container->set($id, $service);
            $this->addServiceForIsolation($id, $service);
        } else {
            throw new \Exception("The container has been isolated");
        }
    }

    /**
     * Get a service in isolated environment
     * @param string $id
     * @param int $invalidBehavior
     * @return mixed|object
     * @throws \Exception
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        if (!$this->isolated) {
            throw new \Exception("The fake container has not been isolated yet. Impossible to get service");
        }

        if ($id == "service_container") {
            return $this;
        }

        if (isset($this->isolatedService[$id])) {
            return $this->isolatedService[$id];
        }

        if (isset($this->servicesDefinition[$id])) {
            $class = $this->servicesDefinition[$id]["class"];
            foreach ($this->servicesDefinition[$id]["params"] as $paramDefinition) {
                if (substr($paramDefinition, 0, 1) == "%" && substr($paramDefinition, strlen($paramDefinition) - 1, 1) == "%") {
                    $params[] = $this->getParameter(substr($paramDefinition, 1, strlen($paramDefinition) - 2));
                } else if ($paramDefinition == "@sebk_small_orm_dao") {
                    $params[] = $this->get("sebk_small_orm_dao");
                } else if (substr($paramDefinition, 0, 1) == "@") {
                    $params[] = $this->get(substr($paramDefinition, 1));
                }
            }

            $this->isolatedService[$id] = new $class(...$params);

            return $this->isolatedService[$id];
        }

        throw new \Exception("This service has not been added for isolation ($id)");
    }

    /**
     * Has a service added in isolated environment
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return isset($this->isolatedService[$id]);
    }

    /**
     * Has a service added in isolated environment
     * @param string $id
     * @return bool
     */
    public function initialized($id)
    {
        return isset($this->isolatedService[$id]);
    }

    /**
     * Get a parameter in isolated environment
     * @param string $name
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->isolatedParameters[$name];
    }

    /**
     * Is parameter has been added in isolated environment
     * @param string $name
     * @return bool
     */
    public function hasParameter($name)
    {
        return isset($this->isolatedParameters[$name]);
    }

    /**
     * Set a parameter value to isolated environment
     * @param string $name
     * @param mixed $value
     */
    public function setParameter($name, $value)
    {
        $this->isolatedParameters[$name] = $value;
    }

    /**
     * Parse a file
     * @param $filepath
     * @throws \Exception
     */
    public function addServicesYaml($filepath)
    {
        // Is array of files
        if (is_array($filepath)) {
            // Add all
            foreach ($filepath as $item) {
                $this->addServicesYaml($item);
            }
            return;
        }

        // Check file exists
        if (!is_file($filepath)) {
            throw new \Exception("FakeContainer::addServicesYaml : File not found ($filepath)");
        }

        // Parse yaml
        $parsedServices = Yaml::parse(file_get_contents($filepath));

        // Check service entry
        if (!isset($parsedServices["services"])) {
            throw new \Exception("FakeContainer::addServicesYaml : File is not a service yaml file ($filepath)");
        }

        // Foreach services entry
        foreach ($parsedServices["services"] as $id => $parsedService) {
            // If class entry
            if (isset($parsedService["class"])) {
                // Get arguments
                $class = $parsedService["class"];
                $parms = [];
                if (isset($parsedService["arguments"])) {
                    $parms = $parsedService["arguments"];
                }

                // And add service to fake container
                $this->addServiceForIsolation($id, $class, $parms);
            }
        }
    }
}
