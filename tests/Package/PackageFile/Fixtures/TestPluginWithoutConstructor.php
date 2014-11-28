<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures;

use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Plugin\PluginInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestPluginWithoutConstructor implements PluginInterface
{
    public function activate(ProjectEnvironment $environment)
    {
    }
}