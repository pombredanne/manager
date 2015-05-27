<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Event\GenerateFactoryEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Php\Clazz;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Manager\Factory\FactoryManagerImpl;
use Puli\Manager\Factory\Generator\DefaultGeneratorRegistry;
use Puli\Manager\Php\ClassWriter;
use Puli\Manager\Tests\ManagerTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FactoryManagerImplTest extends ManagerTestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var DefaultGeneratorRegistry
     */
    private $registry;

    /**
     * @var ClassWriter
     */
    private $realWriter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ClassWriter
     */
    private $fakeWriter;

    /**
     * @var ServerCollection
     */
    private $servers;

    /**
     * @var FactoryManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/FactoryManagerImplTest'.rand(10000, 99999), 0777, true)) {}

        @mkdir($this->tempDir.'/home');
        @mkdir($this->tempDir.'/root');

        $this->initEnvironment($this->tempDir.'/home', $this->tempDir.'/root');

        $this->environment->getConfig()->set(Config::FACTORY_OUT_FILE, 'MyFactory.php');
        $this->environment->getConfig()->set(Config::FACTORY_OUT_CLASS, 'Puli\MyFactory');

        $this->registry = new DefaultGeneratorRegistry();
        $this->realWriter = new ClassWriter();
        $this->fakeWriter = $this->getMockBuilder('Puli\Manager\Php\ClassWriter')
            ->disableOriginalConstructor()
            ->getMock();
        $this->servers = new ServerCollection(array(
            new Server('localhost', 'symlink', 'public_html', '/%s'),
            new Server('example.com', 'rsync', 'ssh://example.com', 'http://example.com/%s'),
        ));
        $this->manager = new FactoryManagerImpl($this->environment, $this->registry, $this->realWriter, $this->servers);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testIsFactoryClassAutoGenerated()
    {
        $this->assertTrue($this->manager->isFactoryClassAutoGenerated());

        $this->environment->getConfig()->set(Config::FACTORY_AUTO_GENERATE, false);

        $this->assertFalse($this->manager->isFactoryClassAutoGenerated());

        $this->environment->getConfig()->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->assertTrue($this->manager->isFactoryClassAutoGenerated());
    }

    public function testGenerateFactoryClass()
    {
        $this->manager->generateFactoryClass();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');

        $expected = <<<EOF
<?php

namespace Puli;

use Puli\Discovery\Api\ResourceDiscovery;
use Puli\Discovery\KeyValueStoreDiscovery;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Repository\Api\ResourceRepository;
use Puli\Repository\FilesystemRepository;
use Puli\UrlGenerator\Api\UrlGenerator;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Creates Puli's core services.
 *
 * This class was auto-generated by Puli.
 *
 * IMPORTANT: Before modifying the code below, set the "factory.auto-generate"
 * configuration key to false:
 *
 *     $ puli config factory.auto-generate false
 *
 * Otherwise any modifications will be overwritten!
 */
class MyFactory
{
    /**
     * Creates the resource repository.
     *
     * @return ResourceRepository The created resource repository.
     */
    public function createRepository()
    {
        if (!file_exists(__DIR__.'/.puli/repository')) {
            mkdir(__DIR__.'/.puli/repository', 0777, true);
        }

        \$repo = new FilesystemRepository(__DIR__.'/.puli/repository', true);

        return \$repo;
    }

    /**
     * Creates the resource discovery.
     *
     * @param ResourceRepository \$repo The resource repository to read from.
     *
     * @return ResourceDiscovery The created resource discovery.
     */
    public function createDiscovery(ResourceRepository \$repo)
    {
        \$store = new JsonFileStore(__DIR__.'/.puli/bindings.json', true);
        \$discovery = new KeyValueStoreDiscovery(\$repo, \$store);

        return \$discovery;
    }

    /**
     * Creates the URL generator.
     *
     * @param ResourceDiscovery \$discovery The resource discovery to read from.
     *
     * @return UrlGenerator The created URL generator.
     */
    public function createUrlGenerator(ResourceDiscovery \$discovery)
    {
        \$generator = new DiscoveryUrlGenerator(\$discovery, array(
            'localhost' => '/%s',
            'example.com' => 'http://example.com/%s',
        ));

        return \$generator;
    }
}

EOF;


        $this->assertSame($expected, $contents);
    }

    public function testGenerateFactoryClassAtCustomRelativePath()
    {
        $this->manager->generateFactoryClass('MyCustomFile.php');

        $this->assertFileExists($this->rootDir.'/MyCustomFile.php');
        $contents = file_get_contents($this->rootDir.'/MyCustomFile.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyFactory', $contents);
    }

    public function testGenerateFactoryClassAtCustomAbsolutePath()
    {
        $this->manager->generateFactoryClass($this->rootDir.'/path/MyCustomFile.php');

        $this->assertFileExists($this->rootDir.'/path/MyCustomFile.php');
        $contents = file_get_contents($this->rootDir.'/path/MyCustomFile.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyFactory', $contents);
    }

    public function testGenerateFactoryClassWithCustomClassName()
    {
        $this->manager->generateFactoryClass(null, 'MyCustomClass');

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyCustomClass', $contents);
    }

    public function testGenerateFactoryClassDispatchesEvent()
    {
        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::GENERATE_FACTORY)
            ->willReturn(true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::GENERATE_FACTORY)
            ->willReturnCallback(function ($eventName, GenerateFactoryEvent $event) {
                $class = $event->getFactoryClass();

                PHPUnit_Framework_Assert::assertTrue($class->hasMethod('createRepository'));
                PHPUnit_Framework_Assert::assertTrue($class->hasMethod('createDiscovery'));

                $class->addMethod(new Method('createCustom'));
            });

        $this->manager->generateFactoryClass();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');
        $this->assertContains('public function createCustom()', $contents);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfPathEmpty()
    {
        $this->manager->generateFactoryClass('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfPathNoString()
    {
        $this->manager->generateFactoryClass(1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfClassNameEmpty()
    {
        $this->manager->generateFactoryClass(null, '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfClassNameNoString()
    {
        $this->manager->generateFactoryClass(null, 1234);
    }

    public function testAutoGenerateFactoryClass()
    {
        $this->manager->autoGenerateFactoryClass();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyFactory', $contents);
    }

    public function testAutoGenerateFactoryClassDoesNothingIfAutoGenerateDisabled()
    {
        $this->environment->getConfig()->set(Config::FACTORY_AUTO_GENERATE, false);

        $this->manager->autoGenerateFactoryClass();

        $this->assertFileNotExists($this->rootDir.'/MyFactory.php');
    }

    public function testAutoGenerateFactoryClassGeneratesWithCustomParameters()
    {
        $this->manager->autoGenerateFactoryClass('MyCustomFile.php', 'MyCustomClass');

        $this->assertFileExists($this->rootDir.'/MyCustomFile.php');
        $contents = file_get_contents($this->rootDir.'/MyCustomFile.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyCustomClass', $contents);
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFound()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('Puli\MyFactory', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyFactory.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFoundAtCustomRelativePath()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('Puli\MyFactory', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyCustomFile.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass('MyCustomFile.php');
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFoundAtCustomAbsolutePath()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('Puli\MyFactory', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyCustomFile.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir.'/path', $class->getDirectory());
            });

        $manager->refreshFactoryClass($this->rootDir.'/path/MyCustomFile.php');
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFoundWithCustomClass()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('MyCustomClass', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyFactory.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass(null, 'MyCustomClass');
    }

    public function testRefreshFactoryClassGeneratesIfOlderThanRootPackageFile()
    {
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        touch($this->rootDir.'/MyFactory.php');
        sleep(1);
        touch($this->rootPackageFile->getPath());

        $this->fakeWriter->expects($this->once())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassGeneratesWithCustomParameters()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        touch($this->rootDir.'/MyCustomFile.php');
        sleep(1);
        touch($this->rootPackageFile->getPath());

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('MyCustomClass', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyCustomFile.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass('MyCustomFile.php', 'MyCustomClass');
    }

    public function testRefreshFactoryClassDoesNotGenerateIfNewerThanRootPackageFile()
    {
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        touch($this->rootPackageFile->getPath());
        sleep(1);
        touch($this->rootDir.'/MyFactory.php');

        $this->fakeWriter->expects($this->never())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassGeneratesIfOlderThanConfigFile()
    {
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        touch($this->rootPackageFile->getPath());
        touch($this->rootDir.'/MyFactory.php');
        sleep(1);
        touch($this->configFile->getPath());

        $this->fakeWriter->expects($this->once())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassDoesNotGenerateIfNewerThanConfigFile()
    {
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        touch($this->rootPackageFile->getPath());
        touch($this->configFile->getPath());
        sleep(1);
        touch($this->rootDir.'/MyFactory.php');

        $this->fakeWriter->expects($this->never())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassDoesNotGenerateIfAutoGenerateDisabled()
    {
        $manager = new FactoryManagerImpl($this->environment, $this->registry, $this->fakeWriter, $this->servers);

        // Older than config file -> would normally be generated
        touch($this->rootDir.'/MyFactory.php');
        sleep(1);
        touch($this->rootPackageFile->getPath());

        $this->fakeWriter->expects($this->never())
            ->method('writeClass');

        $this->environment->getConfig()->set(Config::FACTORY_AUTO_GENERATE, false);

        $manager->refreshFactoryClass();
    }

    public function testCreateFactory()
    {
        $this->assertFalse(class_exists('Puli\Repository\Tests\TestGeneratedFactory1', false));

        $this->environment->getConfig()->set(Config::FACTORY_IN_CLASS, 'Puli\Repository\Tests\TestGeneratedFactory1');

        $factory = $this->manager->createFactory();

        $this->isInstanceOf('Puli\Repository\Tests\TestGeneratedFactory1', $factory);
    }

    public function testCreateFactoryWithCustomParameters()
    {
        $this->assertFalse(class_exists('Puli\Repository\Tests\TestGeneratedFactory2', false));

        $factory = $this->manager->createFactory('MyFactory.php', 'Puli\Repository\Tests\TestGeneratedFactory2');

        $this->isInstanceOf('Puli\Repository\Tests\TestGeneratedFactory2', $factory);
    }
}
