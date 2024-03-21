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
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Plus\ChannelAdmin\Application\Provider\AdminChannelProviderInterface;

final class ChannelRestrictionQueryBuilder implements ChannelRestrictionQueryBuilderInterface
{
    public function __construct(
        /** @phpstan-ignore-next-line  */
        private AdminChannelProviderInterface $adminChannelProvider,
    ) {
    }

    public function createForChannels(QueryBuilder $originalQueryBuilder): QueryBuilder
    {
        /** @var ChannelInterface|null $channel */
        /** @phpstan-ignore-next-line  */
        $channel = $this->adminChannelProvider->getChannel();
        if (null === $channel) {
            return $originalQueryBuilder;
        }

        return $originalQueryBuilder
            ->andWhere(':channel MEMBER OF o.channels')
            ->orWhere('o.channels IS EMPTY')
            ->setParameter('channel', $channel)
        ;
    }

    public function createForChannel(QueryBuilder $originalQueryBuilder): QueryBuilder
    {
        /** @var ChannelInterface|null $channel */
        /** @phpstan-ignore-next-line  */
        $channel = $this->adminChannelProvider->getChannel();
        if (null === $channel) {
            return $originalQueryBuilder;
        }

        return $originalQueryBuilder
            ->andWhere('o.channels = :channel OR o.channels IS EMPTY')
            ->setParameter('channel', $channel)
        ;
    }
}
