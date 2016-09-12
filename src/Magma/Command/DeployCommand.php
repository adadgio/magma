<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
//use Symfony\Component\Process\Process;
use Symfony\Component\Console\Question\Question;
// use Symfony\Component\Console\Helper\ProgressBar;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;

use Magma\Common\Config;
use Magma\Common\Release;
use Magma\Common\CmdBuilder;
use Magma\Common\RsyncOutput;

class DeployCommand extends Command
{
    /**
     * Configure command.
     *
     * @return [type] [description]
     */
    protected function configure()
    {
        $this
            ->setName('deploy')
            ->setDescription('Deploys project to server as a new release')
            ->setHelp('Deploys project to server as a new release using local configuration file')
        ;
    }

    /**
     * Execute command.
     *
     * @param  InputInterface  $input  [description]
     * @param  OutputInterface $output [description]
     * @return [type]                  [description]
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = new Config();
        $release = new Release(); // create a new release (and name!)

        $helper = $this->getHelper('question');

        // read the config to find available environments
        $environments = array_keys($config->getParameter('project.environments'));
        $question = new Question(sprintf('<info>$> Choose your deploy environment target [%s]?</info> ', $environments[0]), $environments[0]);

        $env = $helper->ask($input, $output, $question);
        if (!in_array($env, $environments)) {
            throw new \Exception(sprintf('Environment "%s" does not exist', $env));
        }

        // release setup
        $this->runSubCommand('release:setup', $input, $output, $env, $release);
        $this->runSubCommand('release:upload', $input, $output, $env, $release);
        $this->runSubCommand('release:share', $input, $output, $env, $release);
        $this->runSubCommand('release:predeploy', $input, $output, $env, $release);
        $this->runSubCommand('cache:permissions', $input, $output, $env, $release);
        $this->runSubCommand('release:publish', $input, $output, $env, $release);
        $this->runSubCommand('release:postdeploy', $input, $output, $env, $release);
    }
    
    private function runSubCommand($name, InputInterface $input, OutputInterface $output, $env, $release)
    {


        $command = $this->getApplication()->find($name);
        $arrayInput = new ArrayInput(array(
            'command'   => $name,
            'env'       => $env,
            'release'   => $release->getName(),
        ));

        return $command->run($arrayInput, $output);
    }
}
