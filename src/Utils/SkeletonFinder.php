<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 5/31/16
 * Time: 9:30 PM
 */

namespace Foundation\Bundle\Utils;


use Foundation\Bundle\FoundationBundle;
use Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class SkeletonFinder {

    public static function getSkeletonDirs(BundleInterface $bundle = null, $kernelRootDir) {
        $skeletonDirs = array();

        if (isset($bundle) && is_dir($dir = "{$bundle->getPath()}/Resources/SensioGeneratorBundle/skeleton")) {
            $skeletonDirs[] = $dir;
        }

        if (is_dir($dir = "$kernelRootDir/Resources/SensioGeneratorBundle/skeleton")) {
            $skeletonDirs[] = $dir;
        }

        $bundle = new FoundationBundle();

        if (is_dir($dir = "{$bundle->getPath()}/Resources/skeleton")) {
            $skeletonDirs[] = $dir;
        }

        $bundle = new SensioGeneratorBundle();

        if (is_dir($dir = "{$bundle->getPath()}/Resources/skeleton")) {
            $skeletonDirs[] = $dir;
        }

        if (is_dir($dir = "{$bundle->getPath()}/Resources")) {
            $skeletonDirs[] = $dir;
        }

        return $skeletonDirs;
    }
    
}