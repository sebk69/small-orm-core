<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Factory;

use \Sebk\SmallOrmCore\Dao\Model;
use Sebk\SmallOrmCore\Validator\AbstractValidator;

/**
 *
 */
class Validator
{
    protected $daoFactory;
    protected $config;

    /**
     * Construct dao factory
     * @param \Sebk\SmallOrmCore\Factory\Connections $connectionFactory
     * @param type $config
     */
    public function __construct($daoFactory, $config)
    {
        $this->daoFactory = $daoFactory;
        $this->config     = $config;
    }

    /**
     * Get validator of a model
     * @param Model $model
     * @return AbstractValidator
     * @throws ConfigurationException
     * @throws DaoNotFoundException
     */
    public function get(Model $model)
    {
        $className = $model->getValidator();
        return new $className($this->daoFactory, $model);

        throw new DaoNotFoundException("Validator of model " . get_class($model) . " not found");
    }
}
