<h1 align="center">Sylius Plus Adapter Plugin</h1>

[![Menu Plugin license](https://img.shields.io/github/license/monsieurbiz/SyliusPlusAdapterPlugin?public)](https://github.com/monsieurbiz/SyliusPlusAdapterPlugin/blob/master/LICENSE.txt)
[![Tests Status](https://img.shields.io/github/actions/workflow/status/monsieurbiz/SyliusPlusAdapterPlugin/tests.yaml?branch=master&logo=github)](https://github.com/monsieurbiz/SyliusPlusAdapterPlugin/actions?query=workflow%3ATests)
[![Recipe Status](https://img.shields.io/github/actions/workflow/status/monsieurbiz/SyliusPlusAdapterPlugin/recipe.yaml?branch=master&label=recipes&logo=github)](https://github.com/monsieurbiz/SyliusPlusAdapterPlugin/actions?query=workflow%3ASecurity)
[![Security Status](https://img.shields.io/github/actions/workflow/status/monsieurbiz/SyliusPlusAdapterPlugin/security.yaml?branch=master&label=security&logo=github)](https://github.com/monsieurbiz/SyliusPlusAdapterPlugin/actions?query=workflow%3ASecurity)

This plugin offer tools to adapt your plugins to Sylius Plus RBAC system. 

## Compatibility

| Sylius Version | PHP Version |
|---|---|
| 1.11 | 8.0 - 8.1 |
| 1.12 | 8.1 - 8.2 |
| 1.13 | 8.1 - 8.2 |

## Installation

```bash
composer config --no-plugins --json extra.symfony.endpoint '["https://api.github.com/repos/monsieurbiz/symfony-recipes/contents/index.json?ref=flex/master","flex://defaults"]'
```

```bash
composer require monsieurbiz/sylius-plus-adapter-plugin
```

## How to use

To illustrate the following examples, we will use a `Foo` plugin with a `MyResource` resource.

***Your resource entity***
```php
namespace Foo\SyliusBarPlugin\Entity;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

class MyResource implements ResourceInterface, ChannelsAwareInterface
{
    // ...
}
```
***Your resource config***
```yaml
sylius_resource:
    resources:
        foo_bar.my_resource:
            classes:
                model: Foo\SyliusBarPlugin\Entity\MyResource
```

### Add permissions on routes

It's a Sylius native feature, you don't have to install this plugin!   
You just have to add `permission: true` on your route definition.    
If you do this, your route will become available on the permission tree. 

#### Example

```yaml
foo_bar_my_resource_admin:
    resource: |
        alias: foo_bar.my_resource
        section: admin
        permission: true
        templates: "@SyliusAdmin\\Crud"
        redirect: update
        grid: foo_bar_my_resource
    type: sylius.resource
```

### Add channel restriction on resources

If you want to add channel restrictions on your channel related resources, you have to following theses 2 steps:

* Your resource (entity) need to implement `\Sylius\Component\Channel\Model\ChannelAwareInterface` or `\Sylius\Component\Channel\Model\ChannelsAwareInterface`.
* You need to include the `\MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection\SyliusPlusCompatibilityTrait` trait in your bundle extension class and call the `prependRestrictedResources` method in the `prepend` method.

#### Example

***Your plugin extension file***
```php
namespace Foo\SyliusBarPlugin\DependencyInjection;

use MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection\SyliusPlusCompatibilityTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class FooSyliusBarPluginExtension extends Extension implements PrependExtensionInterface
{
    use SyliusPlusCompatibilityTrait;
    
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependRestrictedResources($container, ['my_resource']);
    }
}
```

### Filter resource grid with channel restriction

To filter the resource grid with channel restriction, you need to include the `\MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection\SyliusPlusCompatibilityTrait` 
trait in your bundle extension class and call the `replaceInGridOriginalQueryBuilderWithChannelRestrictedQueryBuilder` method in the `prepend` method.   
The goal of this is to replace the original query builder of the grid with a new one that call the original one but will filter the resources with the current channel if needed.   
    
Configuring this is tricky so follow the example below.

#### Example

***Your current resource grid config***
```yaml
sylius_grid:
    grids:
      foo_bar_my_resource:
            driver:
                name: doctrine/orm
                options:
                    class: '%foo.model.my_resource.class%'
                    repository:
                        method: createListQueryBuilder
                        arguments: ["%locale%"]
            # ...
```
***Your plugin extension file***
```php
namespace Foo\SyliusBarPlugin\DependencyInjection;

use MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection\SyliusPlusCompatibilityTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class FooSyliusBarPluginExtension extends Extension implements PrependExtensionInterface
{
    use SyliusPlusCompatibilityTrait;
    
    public function prepend(ContainerBuilder $container): void
    {
        $this->replaceInGridOriginalQueryBuilderWithChannelRestrictedQueryBuilder(
            $container,
            'foo_bar_my_resource', // This is the grid name
            '%foo_bar.model.my_resource.class%', // This is the resource class as in your original grid
            "expr:service('foo_bar.repository.my_resource').createListQueryBuilder('%locale%')" // This is the original query builder but called as an expression
        );
    }
}
```

### Filter channel choice type with channel restriction

To filter the channel choice type with channel restriction, you need to include the `\MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection\SyliusPlusCompatibilityTrait` and call the `enabledFilteredChannelChoiceType` method in the `load` method.

#### Example

***Your current resource form type***
```php
namespace Foo\SyliusBarPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\FormBuilderInterface;

class MyResourceType extends AbstractResourceType
{
    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channels', ChannelChoiceType::class, [
                'label' => 'fo_bar_my_resource.ui.form.channels',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
            ])
            // ...
        ;
    }
```
***Your plugin extension file***
```php
namespace Foo\SyliusBarPlugin\DependencyInjection;

use Foo\SyliusBarPlugin\Form\Type\MyResourceType;
use MonsieurBiz\SyliusPlusAdapterPlugin\DependencyInjection\SyliusPlusCompatibilityTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class FooSyliusBarPluginExtension extends Extension implements PrependExtensionInterface
{
    use SyliusPlusCompatibilityTrait;
    
    public function load(array $config, ContainerBuilder $container): void
    {
        // Loading your plugin configuration ...
        $this->enabledFilteredChannelChoiceType($container, ['my_resource' => MyResourceType::class]);
    }
}
```

## Development

Because this plugin is a kind of sidekick for your plugins, it's not intended to be used in a standalone project.   
Even more because it requires Sylius Plus to be useful. Our traditional test application then seems useless.

But to be fair, we still added a test app with our CMS plugin installed and configured to use the `SyliusPlusCompatibilityTrait` trait.   
It would be useful to be sure that everything is working as expected in a normal Sylius even if this plugin is installed and used.

## License

This plugin is under the MIT license.
Please see the [LICENSE](LICENSE) file for more information.
