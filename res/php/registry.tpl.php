
<?php if ($namespace): ?>
namespace <?php echo $namespace ?>;
<?php endif ?>

<?php if (count($imports) > 0): ?>
<?php foreach ($imports as $import): ?>
<?php if ($namespace || false !== strpos($import, '\\')): ?>
use <?php echo $import ?>;
<?php endif ?>
<?php endforeach ?>
<?php endif ?>

/**
 * Provides access to Puli's core services.
 *
 * This class was auto-generated by Puli.
 *
 * IMPORTANT: Before modifying the code below, set the "generate-registry"
 * configuration key to false:
 *
 *     $ puli config generate-registry false
 *
 * Otherwise any modifications will be overwritten!
 */
class <?php echo $shortClassName."\n" ?>
{
    /**
     * @var ResourceRepository
     */
    private static $repository;

    /**
     * @var ResourceDiscovery
     */
    private static $discovery;

    /**
     * Returns the resource repository.
     *
     * @return ResourceRepository The global resource repository.
     */
    public static function getRepository()
    {
        if (!self::$repository) {
            self::$repository = self::loadRepository();
        }

        return self::$repository;
    }

    /**
     * Returns the resource discovery.
     *
     * @return ResourceRepository The global resource discovery.
     */
    public static function getDiscovery()
    {
        if (!self::$discovery) {
            self::$discovery = self::loadDiscovery();
        }

        return self::$discovery;
    }

    /**
     * Loads the resource repository.
     *
     * @return ResourceRepository The loaded resource repository.
     */
    private static function loadRepository()
    {
<?php foreach ($repoDeclarations as $declaration): ?>
<?php echo $this->indent($declaration, 8)."\n\n" ?>
<?php endforeach ?>
        return <?php echo $repoVarName ?>;
    }

    /**
     * Loads the resource discovery.
     *
     * @return ResourceRepository The loaded resource discovery.
     */
    private static function loadDiscovery()
    {
<?php foreach ($discoveryDeclarations as $declaration): ?>
<?php echo $this->indent($declaration, 8)."\n\n" ?>
<?php endforeach ?>
        return <?php echo $discoveryVarName ?>;
    }
}