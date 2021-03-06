<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyrightt 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

/**
 *
 */
class LeftJoinBuilder extends JoinBuilder
{

    /**
     *
     * @param null $type
     * @return string
     */
    public function getSql($type = null)
    {
        return parent::getSql("LEFT JOIN");
    }
}
