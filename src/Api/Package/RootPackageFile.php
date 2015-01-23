<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Package;

use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\InvalidConfigException;
use ReflectionClass;
use ReflectionException;

/**
 * The package file of the root package.
 *
 * You can pass a base configuration to the constructor that the package's
 * configuration will inherit.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFile extends PackageFile
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var string[]
     */
    private $overrideOrder = array();

    /**
     * @var InstallInfo[]
     */
    private $installInfos = array();

    /**
     * @var string[]
     */
    private $pluginClasses = array();

    /**
     * Creates a new root package file.
     *
     * The file's configuration will inherit its settings from the base
     * configuration passed to the constructor.
     *
     * @param string|null $packageName The package name. Optional.
     * @param string|null $path        The path where the configuration is
     *                                 stored or `null` if this configuration is
     *                                 not stored on the file system.
     * @param Config      $baseConfig  The configuration that the package will
     *                                 inherit its configuration values from.
     */
    public function __construct($packageName = null, $path = null, Config $baseConfig = null)
    {
        parent::__construct($packageName, $path);

        $this->config = new Config($baseConfig);
    }

    /**
     * Returns the configuration of the package.
     *
     * @return Config The configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns the order in which packages should be loaded.
     *
     * If packages contain resource mappings for the same resource paths, this
     * setting can be used to specify in which order these packages should be
     * loaded. Alternatively, you can use {@link setOverriddenPackages()} to
     * mark one of the packages to override the other one.
     *
     * @return string[] A list of package names.
     */
    public function getOverrideOrder()
    {
        return $this->overrideOrder;
    }

    /**
     * Sets the order in which packages should be loaded.
     *
     * If packages contain resource mappings for the same resource paths, this
     * setting can be used to specify in which order these packages should be
     * loaded. Alternatively, you can use {@link setOverriddenPackages()} to
     * mark one of the packages to override the other one.
     *
     * @param string[] $overrideOrder A list of package names.
     */
    public function setOverrideOrder(array $overrideOrder)
    {
        $this->overrideOrder = $overrideOrder;
    }

    /**
     * Returns the install infos of all installed packages.
     *
     * @return InstallInfo[] The install infos.
     */
    public function getInstallInfos()
    {
        // The package names as array keys are for internal use only
        return array_values($this->installInfos);
    }

    /**
     * Sets the install infos of all installed packages.
     *
     * @param InstallInfo[] The install infos.
     */
    public function setInstallInfos(array $installInfos)
    {
        $this->installInfos = array();

        foreach ($installInfos as $installInfo) {
            $this->addInstallInfo($installInfo);
        }
    }

    /**
     * Adds install info for an installed package.
     *
     * @param InstallInfo $installInfo The install info.
     */
    public function addInstallInfo(InstallInfo $installInfo)
    {
        $this->installInfos[$installInfo->getPackageName()] = $installInfo;
    }

    /**
     * Removes the install info of an installed package.
     *
     * @param string $packageName The package name.
     */
    public function removeInstallInfo($packageName)
    {
        unset($this->installInfos[$packageName]);
    }

    /**
     * Returns the install info of an installed package.
     *
     * @param string $packageName The package name.
     *
     * @return InstallInfo The install info.
     *
     * @throws NoSuchPackageException If no package is installed with that name.
     */
    public function getInstallInfo($packageName)
    {
        if (!isset($this->installInfos[$packageName])) {
            throw new NoSuchPackageException(sprintf(
                'Could not get install info: The package "%s" is not installed.',
                $packageName
            ));
        }

        return $this->installInfos[$packageName];
    }

    /**
     * Returns whether an install info with a given name exists.
     *
     * @param string $packageName The name of the package.
     *
     * @return bool Whether install info with that name exists.
     */
    public function hasInstallInfo($packageName)
    {
        return isset($this->installInfos[$packageName]);
    }

    /**
     * Returns the plugin classes.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see setPluginClasses()
     */
    public function getPluginClasses()
    {
        return array_keys($this->pluginClasses);
    }

    /**
     * Sets the plugin classes.
     *
     * The plugin classes must be fully-qualified class names that implement
     * {@link Puli\RepositoryManager\Api\Plugin\PuliPlugin}. If a class is not
     * found or does not implement that interface, an exception is thrown.
     *
     * The plugin classes must not have required parameters in their constructor
     * so that they can be successfully instantiated. If a constructor has
     * required parameters, an exception is thrown.
     *
     * Leading backslashes are removed from the fully-qualified class names.
     *
     * @param string[] $pluginClasses The fully qualified plugin class names.
     *
     * @throws InvalidConfigException If the class is not found, is not a class,
     *                                does not implement {@link PuliPlugin}
     *                                or has required constructor parameters.
     */
    public function setPluginClasses(array $pluginClasses)
    {
        $this->pluginClasses = array();

        foreach ($pluginClasses as $pluginClass) {
            $this->addPluginClass($pluginClass);
        }
    }

    /**
     * Adds a plugin class.
     *
     * The plugin class must be a fully-qualified class name that implements
     * {@link PuliPlugin}. If the class is not found or does not implement
     * that interface, an exception is thrown.
     *
     * The plugin class must not have required parameters in its constructor
     * so that it can be successfully instantiate. If the constructor has
     * required parameters, an exception is thrown.
     *
     * Leading backslashes are removed from the fully-qualified class name.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If the class is not found, is not a class,
     *                                does not implement {@link PuliPlugin}
     *                                or has required constructor parameters.
     */
    public function addPluginClass($pluginClass)
    {
        try {
            $reflClass = new ReflectionClass($pluginClass);
        } catch (ReflectionException $e) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s does not exist.',
                $pluginClass
            ), 0, $e);
        }

        if ($reflClass->isInterface()) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s should be a class, but is an interface.',
                $pluginClass
            ));
        }

        if (version_compare(PHP_VERSION, '5.4.0', '>=') && $reflClass->isTrait()) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s should be a class, but is a trait.',
                $pluginClass
            ));
        }

        if (!$reflClass->implementsInterface('\Puli\RepositoryManager\Api\Plugin\PuliPlugin')) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s must implement PuliPlugin.',
                $pluginClass
            ));
        }

        $constructor = $reflClass->getConstructor();

        if (null !== $constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidConfigException(sprintf(
                'The constructor of the plugin class %s must not have required '.
                'parameters.',
                $pluginClass
            ));
        }

        $this->pluginClasses[ltrim($pluginClass, '\\')] = true;
    }

    /**
     * Removes a plugin class.
     *
     * If the plugin class has not been added, this method does nothing. This
     * method also does not validate whether the passed value is actually a
     * plugin class.
     *
     * Leading backslashes are removed from the fully-qualified class name.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     */
    public function removePluginClass($pluginClass)
    {
        unset($this->pluginClasses[ltrim($pluginClass, '\\')]);
    }

    /**
     * Returns whether the configuration contains a plugin class.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @return bool Whether the configuration contains the plugin class.
     */
    public function hasPluginClass($pluginClass)
    {
        return isset($this->pluginClasses[ltrim($pluginClass, '\\')]);
    }
}