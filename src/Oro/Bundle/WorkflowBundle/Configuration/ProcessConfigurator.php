<?php

namespace Oro\Bundle\WorkflowBundle\Configuration;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use Oro\Bundle\WorkflowBundle\Entity\ProcessDefinition;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ProcessConfigurator implements LoggerAwareInterface
{
    /** @var ManagerRegistry */
    private $registry;

    /** @var ProcessDefinitionsConfigurator */
    private $definitionsConfigurator;

    /** @var ProcessTriggersConfigurator */
    private $triggersConfigurator;

    /** @var string */
    protected $definitionClass;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param ManagerRegistry $registry
     * @param ProcessDefinitionsConfigurator $definitionsConfigurator
     * @param ProcessTriggersConfigurator $triggersImport
     * @param string $definitionClass
     */
    public function __construct(
        ManagerRegistry $registry,
        ProcessDefinitionsConfigurator $definitionsConfigurator,
        ProcessTriggersConfigurator $triggersImport,
        $definitionClass
    ) {
        $this->registry = $registry;
        $this->definitionsConfigurator = $definitionsConfigurator;
        $this->triggersConfigurator = $triggersImport;
        $this->definitionClass = $definitionClass;
        $this->setLogger(new NullLogger());
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->definitionsConfigurator->setLogger($logger);
        $this->triggersConfigurator->setLogger($logger);
    }

    /**
     * @param array $processConfigurations
     */
    public function configureProcesses(array $processConfigurations = [])
    {

        if (array_key_exists(ProcessConfigurationProvider::NODE_DEFINITIONS, $processConfigurations)) {
            $this->definitionsConfigurator->configureDefinitions(
                $processConfigurations[ProcessConfigurationProvider::NODE_DEFINITIONS]
            );
            $this->definitionsConfigurator->flush();
        }

        if (array_key_exists(ProcessConfigurationProvider::NODE_TRIGGERS, $processConfigurations)) {
            $this->triggersConfigurator->configureTriggers(
                $processConfigurations[ProcessConfigurationProvider::NODE_TRIGGERS],
                $this->getRepository()->findAll()
            );
            $this->triggersConfigurator->flush();
        }
    }

    /**
     * Removes all process definitions from database by their names
     *
     * @param array|string $names
     */
    public function removeProcesses($names)
    {
        $repository = $this->getRepository();

        foreach ((array)$names as $processDefinitionName) {
            /** @var ProcessDefinition $definition */
            $definition = $repository->find($processDefinitionName);
            if ($definition) {
                $this->triggersConfigurator->removeDefinitionTriggers($definition);
                $this->definitionsConfigurator->removeDefinition($processDefinitionName);
            }
        }
        
        $this->triggersConfigurator->flush();
        $this->definitionsConfigurator->flush();
    }

    /**
     * @return ObjectRepository
     */
    protected function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->definitionClass);
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectManager|null
     */
    protected function getObjectManager()
    {
        return $this->registry->getManagerForClass($this->definitionClass);
    }
}
