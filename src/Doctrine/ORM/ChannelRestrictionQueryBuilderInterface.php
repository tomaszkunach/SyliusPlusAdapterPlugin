<?php

/*
 * This file is part of Monsieur Biz's  for Sylius.
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MonsieurBiz\SyliusPlusAdapterPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;

interface ChannelRestrictionQueryBuilderInterface
{
    public function createForChannels(QueryBuilder $originalQueryBuilder): QueryBuilder;

    public function createForChannel(QueryBuilder $originalQueryBuilder): QueryBuilder;
}
