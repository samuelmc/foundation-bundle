<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 4/3/16
 * Time: 5:31 PM
 */

namespace Foundation\Bundle\Manipulator;


use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;

class FdnRoutingManipulator extends RoutingManipulator {

    private $file;

    /**
     * Constructor.
     *
     * @param string $file The YAML routing file path
     */
    public function __construct($file) {
        parent::__construct($file);
        $this->file = $file;
    }
    
    /**
     * Adds a routing resource at the top of the existing ones.
     *
     * @param string $bundle
     * @param string $format
     * @param string $prefix
     * @param string $path
     *
     * @return bool true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already imported
     */
    public function addResource($bundle, $format, $prefix = '/', $path = 'routing')
    {
        $current = '';
        $code = sprintf("%s:\n", $this->getImportedResourceYamlKey($bundle, $prefix));

        if (file_exists($this->file)) {
            $current = file_get_contents($this->file);

            // Don't add same bundle twice
            if (false !== strpos($current, "@$bundle/Resources/config/routing.")) {
                throw new \RuntimeException(sprintf('Bundle "%s" is already imported.', $bundle));
            }
        } elseif (!is_dir($dir = dirname($this->file))) {
            mkdir($dir, 0777, true);
        }

        if ('annotation' == $format) {
            $code .= sprintf("    resource: \"@%s/Controller/\"\n    type:     annotation\n", $bundle);
        } else {
            $code .= sprintf("    resource: \"@%s/Resources/config/%s.%s\"\n", $bundle, $path, $format);
        }
        $code .= sprintf("    prefix:   %s\n", '/');
        $code .= "\n";
        $code .= $current;

        if (false === file_put_contents($this->file, $code)) {
            return false;
        }

        return true;
    }

}
