<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Webmozart\PathUtil\Path;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * Creates a repository manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     */
    public function __construct(ProjectEnvironment $environment, PackageCollection $packages, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->config = $environment->getConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->repo = $environment->getRepository();
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
    }

    /**
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Adds a resource mapping to the repository.
     *
     * @param ResourceMapping $mapping The resource mapping.
     */
    public function addResourceMapping(ResourceMapping $mapping)
    {
        foreach ($mapping->getFilesystemPaths() as $relPath) {
            $absPath = Path::makeAbsolute($relPath, $this->rootDir);
            $resource = is_dir($absPath)
                ? new DirectoryResource($absPath)
                : new FileResource($absPath);

            $this->repo->add($mapping->getRepositoryPath(), $resource);
        }

        $this->rootPackageFile->addResourceMapping($mapping);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Removes a resource mapping from the repository.
     *
     * @param string $repositoryPath The repository path.
     */
    public function removeResourceMapping($repositoryPath)
    {
        $this->repo->remove($repositoryPath);

        $this->rootPackageFile->removeResourceMapping($repositoryPath);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Returns whether a repository path is mapped.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return bool Returns `true` if the repository path is mapped.
     */
    public function hasResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->hasResourceMapping($repositoryPath);
    }

    /**
     * Returns the resource mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return ResourceMapping The corresponding resource mapping.
     *
     * @throws NoSuchMappingException If the repository path is not mapped.
     */
    public function getResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->getResourceMapping($repositoryPath);
    }

    /**
     * Returns the resource mappings.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     *
     * @return ResourceMapping[] The resource mappings.
     */
    public function getResourceMappings($packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $mappings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getResourceMappings() as $mapping) {
                $mappings[] = $mapping;
            }
        }

        return $mappings;
    }

    /**
     * Builds the resource repository.
     *
     * @throws NoDirectoryException If the dump directory exists and is not a
     *                              directory.
     * @throws ResourceConflictException If two packages contain conflicting
     *                                   resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function buildRepository()
    {
        $repo = $this->environment->getRepository();

        $builder = new RepositoryBuilder();
        $builder->loadPackages($this->packages);

        if ($repo->hasChildren('/')) {
            $repo->clear();
        }

        $builder->buildRepository($repo);
    }
}
