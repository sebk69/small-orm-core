<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyrightt 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

/**
 *
 */
class FullOuterJoinBuilder extends JoinBuilder
{

    /**
     *
     * @param null $type
     * @return string
     * @throws QueryBuilderException
     */
    public function getSql($type = null)
    {
        throw new QueryBuilderException("Full outer join is not now implemented");

        return parent::getSql("FULL OUTER JOIN");
    }
}