<?php

/*
 * This file is part of Monsieur Biz' Sylius Plus Adapter plugin for Sylius.
 *
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection;

use MonsieurBiz\SyliusPlusAdapterPlugin\Doctrine\ORM\ChannelRestrictionQueryBuilderInterface;
use MonsieurBiz\SyliusPlusAdapterPlugin\Form\Extension\FilteredChannelChoiceTypeExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

trait SyliusPlusCompatibilityTrait
{
    public function enabledFilteredChannelChoiceType(ContainerBuilder $container, array $extendables): void
    {
        if (false === $this->isSyliusPlusInstalled($container)) {
            $container->removeDefinition(FilteredChannelChoiceTypeExtension::class);

            return;
        }

        // For each type with a channel choice list to filter we create a "virtual" type extension based on the FilteredChannelChoiceTypeExtension
        // class to override channel(s) fields of original types.
        foreach ($extendables as $code => $type) {
            $container->setDefinition(
                'monsieurbiz_sylius_plus_adapter.form_extension.filtered_channel_choice_type.' . $code,
                (new Definition(FilteredChannelChoiceTypeExtension::class))
                    ->setAutowired(true)
                    ->addMethodCall('addExtendedType', [$type])
                    ->addTag('form.type_extension', ['extended_type' => $type])
            );
        }

        $container->removeDefinition(FilteredChannelChoiceTypeExtension::class);
    }

    public function prependRestrictedResources(ContainerBuilder $container, array $resources): void
    {
        if (false === $this->isSyliusPlusInstalled($container)) {
            return;
        }

        $container->prependExtensionConfig('sylius_plus', [
            'channel_admin' => ['restricted_resources' => array_fill_keys($resources, null)],
        ]);
    }

    public function isSyliusPlusInstalled(ContainerBuilder $container): bool
    {
        return isset($container->getParameter('kernel.bundles')['SyliusPlusPlugin']);
    }

    /**
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function replaceInGridOriginalQueryBuilderWithChannelRestrictedQueryBuilder(
        ContainerBuilder $container,
        string $grid,
        string $class,
        string $originalQueryBuilderExpression,
        bool $hasMultipleChannels = true
    ): void {
        if (false === $this->isSyliusPlusInstalled($container)) {
            return;
        }

        // Add alias because class name in `expr:service` seems to not work
        $container->setAlias(
            'monsieurbiz_sylius_plus_adapter.channel_restricted_query_builder',
            ChannelRestrictionQueryBuilderInterface::class
        )->setPublic(true);

        // Override grid repository method to use a kind of decorator around the original query builder to add
        // channel restrictions if needed.
        $container->loadFromExtension('sylius_grid', [
            'grids' => [$grid => ['driver' => ['options' => [
                'class' => $class,
                'repository' => [
                    'method' => [
                        "expr:service('monsieurbiz_sylius_plus_adapter.channel_restricted_query_builder')",
                        $hasMultipleChannels ? 'createForChannels' : 'createForChannel',
                    ],
                    'arguments' => [$originalQueryBuilderExpression],
                ],
            ]]]],
        ]);
    }
}
