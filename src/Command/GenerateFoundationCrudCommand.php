<?php

namespace Foundation\Bundle\Command;

use Foundation\Bundle\Generator\FdnCrudGenerator;
use Foundation\Bundle\Utils\SkeletonTrait;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCrudCommand;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Foundation\Bundle\Manipulator\FdnRoutingManipulator as RoutingManipulator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Sensio\Bundle\GeneratorBundle\Command\AutoComplete\EntitiesAutoCompleter;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Command\Command;

class GenerateFoundationCrudCommand extends GenerateDoctrineCrudCommand {
    use SkeletonTrait;

    protected function configure() {
        parent::configure();
        $this->setName('foundation:generate:crud')
             ->setAliases(['generate:foundation:crud'])
             ->setDescription('Generates a CRUD based on a Doctrine entity with a custom route parameter for the entity');
    }

    /**
     * @param array|InputDefinition $definition
     * @return Command
     */
    public function setDefinition($definition) {
        $definition[] = new InputOption('route-entity-parameter', '', InputOption::VALUE_REQUIRED, 'The route parameter witch specifies the entity', 'id');
        /** @var InputOption $inputOption */
        foreach ($definition as &$inputOption) {
            if ($inputOption->getName() === 'format') $inputOption->setDefault('yml');
        }
        return parent::setDefinition($definition);
    }

    protected function getRouteEntityParameter(InputInterface $input, $entity) {
        return $input->getOption('route-entity-parameter') ?: strtolower(str_replace(array('\\', '/'), '_', $entity));
    }

    /*protected function getSkeletonDirs(BundleInterface $bundle = null) {
        return SkeletonFinder::getSkeletonDirs($bundle, $this->getContainer()->get('kernel')->getRootDir());
    }*/

    protected function createGenerator($bundle = null) {
        return new FdnCrudGenerator(
            $this->getContainer()->get('filesystem'),
            $this->getContainer()->getParameter('kernel.root_dir')
        );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $format = Validators::validateFormat($input->getOption('format'));
        $prefix = $this->getRoutePrefix($input, $entity);
        $parameter = $this->getRouteEntityParameter($input, $entity);
        $withWrite = $input->getOption('with-write');
        $forceOverwrite = $input->getOption('overwrite');

        $questionHelper->writeSection($output, 'CRUD generation');

        try {
            $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
            $metadata = $this->getEntityMetadata($entityClass);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Entity "%s" does not exist in the "%s" bundle. Create it with the "doctrine:generate:entity" command and then execute this command again.', $entity, $bundle));
        }

        $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);

        /** @var FdnCrudGenerator $generator */
        $generator = $this->getGenerator($bundle);
        $generator->generate($bundle, $entity, $metadata[0], $format, $prefix, $parameter, $withWrite, $forceOverwrite);

        $output->writeln('Generating the CRUD code: <info>OK</info>');

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        // form
        if ($withWrite) {
            $this->generateForm($bundle, $entity, $metadata, $forceOverwrite);
            $output->writeln('Generating the Form code: <info>OK</info>');
        }

        // routing
        $output->write('Updating the routing: ');
        if ('annotation' != $format) {
            $runner($this->updateRouting($questionHelper, $input, $output, $bundle, $format, $entity, $prefix));
        } else {
            $runner($this->updateAnnotationRouting($bundle, $entity, $prefix));
        }

        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function updateRouting(QuestionHelper $questionHelper, InputInterface $input, OutputInterface $output, BundleInterface $bundle, $format, $entity, $prefix) {
        $auto = true;
        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Confirm automatic update of the Routing', 'yes', '?'), true);
            $auto = $questionHelper->ask($input, $output, $question);
        }

        $output->write('Importing the CRUD routes: ');
        $this->getContainer()->get('filesystem')->mkdir($bundle->getPath().'/Resources/config/');

        // first, import the routing file from the bundle's main routing.yml file
        $routing = new RoutingManipulator($bundle->getPath().'/Resources/config/routing.yml');
        try {
            $ret = $auto ? $routing->addResource($bundle->getName(), $format, '/'.$prefix, 'routing/'.strtolower(str_replace('\\', '_', $entity))) : false;
        } catch (\RuntimeException $exc) {
            $output->writeln($exc->getMessage());
            $ret = false;
        }

        if (!$ret) {
            $help = sprintf("        <comment>resource: \"@%s/Resources/config/routing/%s.%s\"</comment>\n", $bundle->getName(), strtolower(str_replace('\\', '_', $entity)), $format);
            $help .= "        <comment>prefix:   /</comment>\n";

            return array(
                '- Import the bundle\'s routing resource in the bundle routing file',
                sprintf('  (%s).', $bundle->getPath().'/Resources/config/routing.yml'),
                '',
                sprintf('    <comment>%s:</comment>', $routing->getImportedResourceYamlKey($bundle->getName(), $prefix)),
                $help,
                '',
            );
        }

        // second, import the bundle's routing.yml file from the application's routing.yml file
        $routing = new RoutingManipulator($this->getContainer()->getParameter('kernel.root_dir').'/config/routing.yml');
        try {
            $ret = $auto ? $routing->addResource($bundle->getName(), 'yml') : false;
        } catch (\RuntimeException $e) {
            // the bundle is already imported form app's routing.yml file
            $errorMessage = sprintf(
                "\n\n[ERROR] The bundle's \"Resources/config/routing.yml\" file cannot be imported\n".
                "from \"app/config/routing.yml\" because the \"%s\" bundle is\n".
                "already imported. Make sure you are not using two different\n".
                "configuration/routing formats in the same bundle because it won't work.\n",
                $bundle->getName()
            );
            $output->write($errorMessage);
            $ret = true;
        } catch (\Exception $e) {
            $ret = false;
        }

        if (!$ret) {
            return array(
                '- Import the bundle\'s routing.yml file in the application routing.yml file',
                sprintf('# app/config/routing.yml'),
                sprintf('%s:', $bundle->getName()),
                sprintf('    <comment>resource: "@%s/Resources/config/routing.yml"</comment>', $bundle->getName()),
                '',
                '# ...',
                '',
            );
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output) {

        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Doctrine2 CRUD generator');

        // namespace
        $output->writeln(array(
            '',
            'This command helps you generate CRUD controllers and templates.',
            '',
            'First, give the name of the existing entity for which you want to generate a CRUD',
            '(use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>)',
            '',
        ));

        if ($input->hasArgument('entity') && $input->getArgument('entity') != '') {
            $input->setOption('entity', $input->getArgument('entity'));
        }

        $question = new Question($questionHelper->getQuestion('The Entity shortcut name', $input->getOption('entity')), $input->getOption('entity'));
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));

        $autocompleter = new EntitiesAutoCompleter($this->getContainer()->get('doctrine')->getManager());
        $autocompleteEntities = $autocompleter->getSuggestions();
        $question->setAutocompleterValues($autocompleteEntities);
        $entity = $questionHelper->ask($input, $output, $question);

        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        try {
            $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
            $metadata = $this->getEntityMetadata($entityClass);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Entity "%s" does not exist in the "%s" bundle. You may have mistyped the bundle name or maybe the entity doesn\'t exist yet (create it first with the "doctrine:generate:entity" command).', $entity, $bundle));
        }

        // write?
        $withWrite = $input->getOption('with-write') ?: true;
        $output->writeln(array(
            '',
            'By default, the generator creates two actions: list and show.',
            'You can also ask it to generate "write" actions: create, update, and delete.',
            '',
        ));
        $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you want to generate the "write" actions', $withWrite ? 'yes' : 'no', '?'), $withWrite);

        $withWrite = $questionHelper->ask($input, $output, $question);
        $input->setOption('with-write', $withWrite);

        // format
        $format = $input->getOption('format');
        $output->writeln(array(
            '',
            'Determine the format to use for the generated CRUD.',
            '',
        ));
        $question = new Question($questionHelper->getQuestion('Configuration format (yml, xml, php, or annotation)', $format), $format);
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat'));
        $format = $questionHelper->ask($input, $output, $question);
        $input->setOption('format', $format);

        // route prefix
        $prefix = $this->getRoutePrefix($input, $entity);
        $output->writeln(array(
            '',
            'Determine the routes prefix (all the routes will be "mounted" under this',
            'prefix: /prefix/, /prefix/create, ...).',
            '',
        ));
        $prefix = $questionHelper->ask($input, $output, new Question($questionHelper->getQuestion('Routes prefix', '/'.$prefix), '/'.$prefix));
        $input->setOption('route-prefix', $prefix);

        // route entity parameter
        $parameter = $this->getRouteEntityParameter($input, $entity);
        $output->writeln(array(
            '',
            'Determine the routes entity parameter (all the routes will be "mounted" with this',
            "parameter: $prefix/{parameter}, $prefix/{parameter}/edit, ...).",
            'Important! This must be a field of the entity',
            '',
        ));
        $parameter = $questionHelper->ask($input, $output, new Question($questionHelper->getQuestion('Routes entity parameter', $parameter), $parameter));
        $input->setOption('route-entity-parameter', $parameter);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf('You are going to generate a CRUD controller for "<info>%s:%s</info>"', $bundle, $entity),
            sprintf('using the "<info>%s</info>" format.', $format),
            '',
        ));
    }

}
