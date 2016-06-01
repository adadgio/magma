<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;

use Magma\Common\Config;
use Magma\Common\CmdBuilder;
use Magma\Common\PathBuilder;

class PostDeployCommand extends Command
{
    /**
     * Configure command.
     *
     * @return [type] [description]
     */
    protected function configure()
    {
        $this
            ->setName('post:deploy')
            ->setDescription('Execute post deploy commands')
            ->addArgument(
                'env',
                InputArgument::REQUIRED,
                'The deploy environment'
            )
            ->addArgument(
                'release',
                InputArgument::REQUIRED,
                'The name of the release'
            )
            ->setHelp('Execute post deploy commands')
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
        
        $env = $input->getArgument('env');
        $release = $input->getArgument('release');

        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));
        // $shared = $config->getParameter('project.shared');

        $releaseDirPath = $path.'/releases/'.$release;
        $postDeployTasks = $config->getParameter(sprintf('project.environments.%s.post_deploy', $env));

        $commands = array(
            CmdBuilder::export('SYMFONY_ENV', $env),
            CmdBuilder::cd($releaseDirPath),
        );

        foreach ($postDeployTasks as $task) {
            $commands[] = $task;
        }

        $command = CmdBuilder::ssh($user, $host, CmdBuilder::chain($commands));

        // execute process via ssh
        $this->runProcess($input, $output, $command);
    }

    /**
     * [runProcess description]
     * @param  InputInterface  $input   [description]
     * @param  OutputInterface $output  [description]
     * @param  [type]          $command [description]
     * @return [type]                   [description]
     */
    protected function runProcess(InputInterface $input, OutputInterface $output, $command)
    {
        $process = new Process($command);
        $process->setTimeout(null);
        $output->writeln('<info>  $> executing post deploy tasks</info>');

        $process->run(function ($type, $buffer) use ($output) {
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            // quit on error
            $output->writeln('<info>  $> executing post deploy tasks: </info><error>NOT OK</error>');
            exit(1); // bash code error
            // throw new \Exception('cannot create remote directories');
        }

        $output->writeln('<info>  $> executing post deploy tasks: </info>OK');
    }
}
