<?php

/*
 * This file is part of Monsieur Biz's  for Sylius.
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MonsieurBiz\SyliusPlusAdapterPlugin\Form\Extension;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Plus\ChannelAdmin\Application\Provider\AdminChannelProviderInterface;
use Sylius\Plus\ChannelAdmin\Application\Provider\AvailableChannelsForAdminProviderInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class FilteredChannelChoiceTypeExtension extends AbstractTypeExtension
{
    private const CHANNELS = 'channels';

    private const CHANNEL = 'channel';

    public static array $extendedTypes = [];

    private array $savableChannels = [];

    public function __construct(
        /** @phpstan-ignore-next-line  */
        private AvailableChannelsForAdminProviderInterface $availableChannelsForAdminProvider,
        /** @phpstan-ignore-next-line  */
        private AdminChannelProviderInterface $adminChannelProvider,
    ) {
    }

    /**
     * Use this one to dynamically add types when bundle is configured.
     *
     * @see \MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection\SyliusPlusCompatibilityTrait::enabledFilteredChannelChoiceType
     */
    public static function addExtendedType(string $type): void
    {
        self::$extendedTypes[] = $type;
    }

    public static function getExtendedTypes(): iterable
    {
        return self::$extendedTypes;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (false === $builder->has(self::CHANNELS) && false === $builder->has(self::CHANNEL)) {
            return;
        }

        $code = false !== $builder->has(self::CHANNELS) ? self::CHANNELS : self::CHANNEL;
        $options = $builder->get($code)->getOptions();
        $builder->add($code, ChannelChoiceType::class, array_merge($options, [
            /** @phpstan-ignore-next-line  */
            'choices' => $this->availableChannelsForAdminProvider->getChannels(),
        ]));

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            /** @phpstan-ignore-next-line  */
            $restrictedAdminChannel = $this->adminChannelProvider->getChannel();
            if (null === $restrictedAdminChannel) {
                return;
            }

            $data = $event->getData();
            if (!$data instanceof ChannelsAwareInterface) {
                return;
            }

            // In case of multiple channels and channel restriction, we need to save the channels that are not available
            // To avoid losing them when saving the form
            $form = $event->getForm();
            /** @var array $availableChannels */
            $availableChannels = $form->get(self::CHANNELS)->getConfig()->getOptions()['choices'] ?? [];

            foreach ($data->getChannels() as $channel) {
                if (false === \in_array($channel, $availableChannels, true)) {
                    $this->savableChannels[] = $channel;
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @phpstan-ignore-next-line  */
            $restrictedAdminChannel = $this->adminChannelProvider->getChannel();
            if (null === $restrictedAdminChannel) {
                return;
            }

            $data = $event->getData();
            if (!$data instanceof ChannelsAwareInterface) {
                return;
            }

            // We can now add the channels that we saved in the POST_SET_DATA event
            /** @var ChannelInterface $channel */
            foreach ($this->savableChannels as $channel) {
                $data->addChannel($channel);
            }

            $this->savableChannels[] = [];
        });
    }
}
