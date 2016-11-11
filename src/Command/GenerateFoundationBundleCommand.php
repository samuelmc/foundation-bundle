<?php

namespace Foundation\Bundle\Command;

use Foundation\Bundle\Generator\FdnBundleGenerator;
use Foundation\Bundle\Utils\SkeletonFinder;
use Sensio\Bundle\GeneratorBundle\Command\GenerateBundleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class GenerateFoundationBundleCommand extends GenerateBundleCommand {

    protected function configure() {
        parent::configure();
        $this->setName('foundation:generate:bundle')
            ->setAliases(array('generate:foundation:bundle'))
            ->setDescription('Generates a Bundle');
    }

    protected function getSkeletonDirs(BundleInterface $bundle = null) {
        return SkeletonFinder::getSkeletonDirs($bundle, $this->getContainer()->get('kernel')->getRootDir());
    }

    protected function createGenerator() {
        return new FdnBundleGenerator($this->getContainer()->get('filesystem'));
    }

    protected function interact(InputInterface $input, OutputInterface $output) {
        $input->setOption('format', 'yml');
        parent::interact($input, $output);
    }
}
