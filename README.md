<p align="center">
    <a href="https://sylius.com" target="_blank">
        <img src="https://demo.sylius.com/assets/shop/img/logo.png" />
    </a>
</p>

<h1 align="center">Upgrade Plugin</h1>

<p align="center">This plugin helps you to upgrade your Sylius app to a new version.</p>

<p align="center"><a href="https://github.com/webgriffe/SyliusUpgradePlugin/actions"><img src="https://github.com/webgriffe/SyliusUpgradePlugin/workflows/Build/badge.svg" alt="Build Status" /></a></p>


## Table of Contents

- [Table of Contents](#table-of-contents)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [License](#license)
- [Credits](#credits)

## Requirements

* PHP `^8.0`
* Sylius `^1.11.2 || ^1.12`

## Installation

1. Run `composer require --dev webgriffe/sylius-upgrade-plugin`

2. Add the plugin to the `config/bundles.php` file:

    ```php
    Webgriffe\SyliusUpgradePlugin\WebgriffeSyliusUpgradePlugin::class => ['dev' => true, 'test' => true],
    ```

## Usage

All features are implemented as **console commands**.

### Template changes

    bin/console webgriffe:upgrade:template-changes <from-version> <to-version> [--theme=PATH_TO_YOUR_THEME] [--legacy] 

Print a list of template files (with extension .html.twig) that changed between two given Sylius versions and that have been overridden in your project: in root "templates" folder and/or in a custom theme.

You have to specify both the versions **from** and **to** you want to compute the changes.

There are two optional parameters:
* **--theme=PATH_TO_YOUR_THEME**, specify the theme folder in which to search for changed files. The path must be relative to the **kernel.project_dir** of your project. You can specify multiple themes by repeating the **--theme** parameter, see the related example in the proper section below;
* **--legacy**, use legacy theme folder structure. From v2.0 of the [SyliusThemeBundle](https://github.com/Sylius/SyliusThemeBundle/) the theme folder structure has changed. The old structure has been deprecated and will be removed in v3.0 as stated [here](https://github.com/Sylius/SyliusThemeBundle/blob/master/UPGRADE.md#upgrade-from-1x-to-20). 


#### Examples

* List of templates that changed between Sylius v1.8.4 and v1.8.8 and that were overridden in your root **templates** folder:

    ```bash
    bin/console webgriffe:upgrade:template-changes v1.8.4 v1.8.8
    ```

* List of templates that changed between Sylius v1.8.8 and v1.9.3 and that were overridden in your root **templates** folder and/or in your **my-website-theme** folder:

    ```bash
    bin/console webgriffe:upgrade:template-changes v1.8.8 v1.9.3 --theme=themes/my-website-theme
    ```

* Like the previous example, but it computes changes for multiple themes, one of which is in the vendor folder:

    ```bash
    bin/console webgriffe:upgrade:template-changes v1.8.8 v1.9.3 --theme=themes/my-website-theme --theme=themes/my-other-theme --theme=vendor/acme/my-vendor-theme
    ```

### Decorated services changes

    bin/console webgriffe:upgrade:service-changes <from-version> <to-version> [--theme=PATH_TO_YOUR_THEME] [--legacy] 

Print a list of services that changed between two given Sylius versions and that have been decorated/overridden in your project.

You have to specify both the versions **from** and **to** you want to compute the changes.

There are two optional parameters:
* **--namespace-prefix=NAMESPACE-PREFIX**, the first part of the namespace of your app services, like "App" in "App\Calculator\PriceCalculator". Default: "App".
* **--alias-prefix=ALIAS-PREFIX**, the first part of the alias of your app services, like "app" in "app.calculator.price". Default: "app".


#### Examples

* List of services that changed between Sylius v1.11.0 and v1.13.0 and that were decorated in your project:

    ```bash
    bin/console webgriffe:upgrade:service-changes v1.11.0 v1.13.0
    ```

* List of services that changed between Sylius v1.11.0 and v1.13.0 and that were decorated in your project but with custom namespace and alias prefixes:

    ```bash
    bin/console webgriffe:upgrade:service-changes v1.11.0 v1.13.0 --namespace-prefix="Webgriffe" --alias-prefix="webgriffe"
    ```


## Contributing

To contribute to this plugin clone this repository, create a branch for your feature or bugfix, do your changes and then make sure al tests are passing.

    ```bash
    $ (cd tests/Application && yarn install)
    $ (cd tests/Application && yarn build)
    $ (cd tests/Application && APP_ENV=test bin/console assets:install public)

    $ (cd tests/Application && APP_ENV=test bin/console doctrine:database:create)
    $ (cd tests/Application && APP_ENV=test bin/console doctrine:schema:create)
    ```

To be able to setup a plugin's database, remember to configure you database credentials in `tests/Application/.env` and `tests/Application/.env.test`.

### Running plugin tests

  - PHPUnit

    ```bash
    vendor/bin/phpunit
    ```

  - PHPSpec

    ```bash
    vendor/bin/phpspec run
    ```

  - Static Analysis

    - Psalm

      ```bash
      vendor/bin/psalm
      ```

    - PHPStan

      ```bash
      vendor/bin/phpstan analyse
      ```

  - Coding Standard

    ```bash
    vendor/bin/ecs check
    ```

## License

This plugin is under the MIT license. See the complete license in the LICENSE file.

## Credits

Developed by [Webgriffe®](https://www.webgriffe.com/).
