<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Foundation\Bundle\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Common\Inflector\Inflector;

/**
 * Generates a CRUD controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FdnCrudGenerator extends Generator
{
    protected $filesystem;
    protected $rootDir;
    protected $routePrefix;
    protected $routeEntityParameter;
    protected $routeNamePrefix;
    /** @var BundleInterface */
    protected $bundle;
    protected $entity;
    protected $entitySingularized;
    protected $entityPluralized;
    protected $metadata;
    protected $format;
    protected $actions;
    protected $generateParameters;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem A Filesystem instance
     * @param string     $rootDir    The root dir
     */
    public function __construct(Filesystem $filesystem, $rootDir)
    {
        $this->filesystem = $filesystem;
        $this->rootDir = $rootDir;
    }

    /**
     * Generate the CRUD controller.
     *
     * @param BundleInterface   $bundle           A bundle object
     * @param string            $entity           The entity relative class name
     * @param ClassMetadataInfo $metadata         The entity class metadata
     * @param string            $format           The configuration format (xml, yaml, annotation)
     * @param string            $routePrefix      The route name prefix
     * @param array             $needWriteActions Whether or not to generate write actions
     *
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $format, $routePrefix, $routeEntityParameter, $needWriteActions, $forceOverwrite)
    {
        $this->routePrefix = $routePrefix;
        $this->routeEntityParameter = $routeEntityParameter;
        $this->routeNamePrefix = self::getRouteNamePrefix($routePrefix);
        $this->actions = $needWriteActions ? array('index', 'show', 'create', 'edit', 'delete') : array('index', 'show');

        if (count($metadata->identifier) != 1) {
            throw new \RuntimeException('The CRUD generator does not support entity classes with multiple or no primary keys.');
        }

        $this->entity = $entity;
        $this->entitySingularized = lcfirst(Inflector::singularize($entity));
        $this->entityPluralized = lcfirst(Inflector::pluralize($entity));
        $this->bundle = $bundle;
        $this->metadata = $metadata;
        $this->setFormat($format);
        $this->setGenerationParameters();

        $this->generateControllerClass($forceOverwrite);

        $dir = sprintf('%s/Resources/views/%s', $this->bundle->getPath(), str_replace('\\', '/', $this->entity));

        if (!file_exists($dir)) {
            $this->filesystem->mkdir($dir, 0777);
        }

        $this->generateViews($dir);

        $this->generateTestClass();
        $this->generateConfiguration();
    }

    /**
     * Sets the configuration format.
     *
     * @param string $format The configuration format
     */
    protected function setFormat($format)
    {
        switch ($format) {
            case 'yml':
            case 'xml':
            case 'php':
            case 'annotation':
                $this->format = $format;
                break;
            default:
                $this->format = 'yml';
                break;
        }
    }

    protected function setGenerationParameters() {
        $parts = explode('\\', $this->entity);
        $entityClass = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $this->generateParameters = array(
            'actions' => $this->actions,
            'route_prefix' => $this->routePrefix,
            'route_prefix_pluralized' => strtolower(Inflector::pluralize($this->routePrefix)),
            'route_entity_parameter' => $this->routeEntityParameter,
            'route_name_prefix' => $this->routeNamePrefix,
            'bundle' => $this->bundle->getName(),
            'entity' => $this->entity,
            'entity_singularized' => $this->entitySingularized,
            'entity_pluralized' => $this->entityPluralized,
            'entity_class' => $entityClass,
            'namespace' => $this->bundle->getNamespace(),
            'entity_namespace' => $entityNamespace,
            'format' => $this->format,
            'form_type_name' => strtolower(str_replace('\\', '_', $this->bundle->getNamespace()).($parts ? '_' : '').implode('_', $parts).'_'.$entityClass),
            'identifier' => $this->metadata->identifier[0],
            'fields' => $this->metadata->fieldMappings,
            'record_actions' => $this->getRecordActions(),
        );
    }

    /**
     * Generates the routing configuration.
     */
    protected function generateConfiguration()
    {
        if (!in_array($this->format, array('yml', 'xml', 'php'))) {
            return;
        }

        $target = sprintf(
            '%s/Resources/config/routing/%s.%s',
            $this->bundle->getPath(),
            strtolower(str_replace('\\', '_', $this->entity)),
            $this->format
        );

        $this->renderFile('crud/config/routing.'.$this->format.'.twig', $target, $this->generateParameters);
    }

    /**
     * Generates the controller class only.
     */
    protected function generateControllerClass($forceOverwrite)
    {
        $dir = $this->bundle->getPath();

        $parts = explode('\\', $this->entity);
        $entityClass = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $target = sprintf(
            '%s/Controller/%s/%sController.php',
            $dir,
            str_replace('\\', '/', $entityNamespace),
            $entityClass
        );

        if (!$forceOverwrite && file_exists($target)) {
            throw new \RuntimeException('Unable to generate the controller as it already exists.');
        }

        $this->renderFile('crud/controller.php.twig', $target, $this->generateParameters);
    }

    /**
     * Generates the functional test class only.
     */
    protected function generateTestClass()
    {
        $parts = explode('\\', $this->entity);
        $entityClass = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $dir = $this->bundle->getPath().'/Tests/Controller';
        $target = $dir.'/'.str_replace('\\', '/', $entityNamespace).'/'.$entityClass.'ControllerTest.php';

        $this->renderFile('crud/tests/test.php.twig', $target, $this->generateParameters);
    }

    protected function generateViews($dir) {
        $this->renderFile('crud/views/index.html.twig.twig', "$dir/index.html.twig", $this->generateParameters);
        $this->renderFile('crud/views/EntityViewMode/entity_detail.html.twig.twig', "$dir/{$this->entity}ViewMode/{$this->entitySingularized}_detail.html.twig", $this->generateParameters);
        $this->renderFile('crud/views/EntityViewMode/entity_teaser.html.twig.twig', "$dir/{$this->entity}ViewMode/{$this->entitySingularized}_teaser.html.twig", $this->generateParameters);
        if (in_array('show', $this->actions)) $this->renderFile('crud/views/show.html.twig.twig', "$dir/show.html.twig", $this->generateParameters);
        if (in_array('create', $this->actions)) $this->renderFile('crud/views/create.html.twig.twig', "$dir/create.html.twig", $this->generateParameters);
        if (in_array('edit', $this->actions)) $this->renderFile('crud/views/edit.html.twig.twig', "$dir/edit.html.twig", $this->generateParameters);
        if (in_array('delete', $this->actions)) $this->renderFile('crud/views/delete.html.twig.twig', "$dir/delete.html.twig", $this->generateParameters);
    }

    /**
     * Returns an array of record actions to generate (edit, show).
     *
     * @return array
     */
    protected function getRecordActions()
    {
        return array_filter($this->actions, function ($item) {
            return in_array($item, array('show', 'edit'));
        });
    }

    public static function getRouteNamePrefix($prefix)
    {
        $prefix = preg_replace('/{(.*?)}/', '', $prefix); // {foo}_bar -> _bar
        $prefix = str_replace('/', '_', $prefix);
        $prefix = preg_replace('/_+/', '_', $prefix);     // foo__bar -> foo_bar
        $prefix = trim($prefix, '_');

        return $prefix;
    }
}
