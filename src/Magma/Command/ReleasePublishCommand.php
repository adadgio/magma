<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use Magma\Common\Config;
use Magma\Common\Release;
use Magma\Common\CmdBuilder;
use Magma\Common\RsyncOutput;

class ReleasePublishCommand extends Command
{
    /**
     * Configure command.
     *
     * @return [type] [description]
     */
    protected function configure()
    {
        $this
            ->setName('release:publish')
            ->setDescription('Finish release publishing process by creating/modifying the current symlink to lastest release')
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
        $release = new Release($input->getArgument('release'));

        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));

        $target = $path.'/releases/'.$release->getName();
        $symlink = $path.'/current';
        
        // create the symlink from current folder to the release
        $lnCmds[] = CmdBuilder::symlink($target, $symlink);

        // create final ssh command
        $command = CmdBuilder::ssh($user, $host, CmdBuilder::chain($lnCmds));

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
        $process->run();

        $output->writeln('<info>  $> publishing release via symlink...</info>');

        if (!$process->isSuccessful()) {
            // quit on error
            $output->writeln('<info>  $> publish release via symlink: </info><error>NOT OK</error>');
            exit(1); // bash code error
            // throw new \Exception('cannot create remote directories');
        }

        $output->writeln('<info>  $> publish release via symlink: </info>OK');
    }
}
