<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Factory;

/**
 * Generates the source code of the Puli factory.
 *
 * The Puli factory can later be used to easily instantiate the resource
 * repository and the resource discovery in both the user's web application and
 * the Puli CLI.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FactoryManager
{
    /**
     * Creates a factory instance.
     *
     * The factory class is regenerated if necessary.
     *
     * By default, the class is stored in the file and with the class name
     * stored in the configuration.
     *
     * @param string|null $path      If not `null`, the file will be generated
     *                               at the given path.
     * @param string|null $className If not `null`, the file will be generated
     *                               with the given class name.
     *
     * @return object The factory instance.
     */
    public function createFactory($path = null, $className = null);

    /**
     * Returns whether the factory class should be generated automatically.
     *
     * @return bool Returns `true` if the class should be generated
     *              automatically and `false` otherwise.
     */
    public function isFactoryClassAutoGenerated();

    /**
     * Generates a factory class file.
     *
     * By default, the class is stored in the file and with the class name
     * stored in the configuration.
     *
     * Attention: This method ignores the {@link Config::FACTORY_AUTO_GENERATE}
     * setting. Consider using {@link autoGenerateFactoryClass()} if you want
     * to respect that setting.
     *
     * @param string|null $path      If not `null`, the file will be generated
     *                               at the given path.
     * @param string|null $className If not `null`, the file will be generated
     *                               with the given class name.
     */
    public function generateFactoryClass($path = null, $className = null);

    /**
     * Auto-generates a factory class file.
     *
     * This method behaves like {@link generateFactoryClass()}, except that the
     * class is not generated if {@link Config::FACTORY_AUTO_GENERATE} is
     * disabled.
     *
     * @param string|null $path      If not `null`, the file will be generated
     *                               at the given path.
     * @param string|null $className If not `null`, the file will be generated
     *                               with the given class name.
     */
    public function autoGenerateFactoryClass($path = null, $className = null);

    /**
     * Regenerates the factory class file if necessary.
     *
     * The file is (re-)generated if:
     *
     *  * The file does not exist.
     *  * The puli.json file was modified.
     *  * The config.json file was modified.
     *
     * The file is not (re-)generated if {@link Config::FACTORY_AUTO_GENERATE}
     * is disabled.
     *
     * By default, the class is stored in the file and with the class name
     * stored in the configuration.
     *
     * @param string|null $path      If not `null`, the file will be generated
     *                               at the given path.
     * @param string|null $className If not `null`, the file will be generated
     *                               with the given class name.
     */
    public function refreshFactoryClass($path = null, $className = null);
}
