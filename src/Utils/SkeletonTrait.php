<?php
/*
 * Created by Samuel Moncarey
 * 24/11/2016
 */

namespace Foundation\Bundle\Utils;

use Foundation\Bundle\FoundationBundle;
use Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SkeletonTrait
 * @package Foundation\Bundle\Utils
 */
trait SkeletonTrait {

    /**
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface|NULL $bundle
     * @return array
     */
    protected function getSkeletonDirs(BundleInterface $bundle = null) {
        $skeletonDirs = [];
        $rootDir      = $this->getContainer()->get('kernel')->getRootdir();

        if (isset($bundle))
            $this->addSkeletonDir($skeletonDirs, "{$bundle->getPath()}/Resources/SensioGeneratorBundle/skeleton");

        $this->addSkeletonDir($skeletonDirs, "$rootDir/Resources/SensioGeneratorBundle/skeleton");

        $foundationBundle = new FoundationBundle();
        $this->addSkeletonDir($skeletonDirs, "{$foundationBundle->getPath()}/Resources/skeleton");

        $sensioGeneratorBundle = new SensioGeneratorBundle();
        $this->addSkeletonDir($skeletonDirs, "{$sensioGeneratorBundle->getPath()}/Resources/skeleton");
        $this->addSkeletonDir($skeletonDirs, "{$sensioGeneratorBundle->getPath()}/Resources");

        return $skeletonDirs;
    }

    /**
     * @param array $skeletonDirs
     * @param string $dir
     */
    private function addSkeletonDir(&$skeletonDirs, $dir) {
        if (is_dir($dir)) $skeletonDirs[] = $dir;
    }

    /** @return ContainerInterface */
    abstract protected function getContainer();

}
