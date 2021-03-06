<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use Magma\Common\Config;
use Magma\Common\CmdBuilder;
use Magma\Common\PathBuilder;

class ReleaseShareCommand extends Command
{
    /**
     * Configure command
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('release:share')
            ->setDescription('Set up symlinks to shared directories in the release (release must be uploaded first)')
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
            ->setHelp('You must provide the environment release name (see command options)')
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
        $shared = $config->getParameter('project.shared');

        $releaseDirPath = $path.'/releases/'.$release;
        // $sharedDirsPath = PathBuilder::create($path.'/shared/', $shared);

        // foreach shared dirs path, create a symlink FROM
        // the release path dir pointing TO the shared path dir
        $lnCmds = array();

        foreach ($shared as $sharedDir) {
            $target = $path.'/shared/'.$sharedDir;
            $symlink = $releaseDirPath.'/'.$sharedDir;

            $lnCmds[] = CmdBuilder::symlink($target, $symlink);
        }

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

        $output->writeln('<info>  $> setting up shared directories symlinks...</info>');

        if (!$process->isSuccessful()) {
            // quit on error
            $output->writeln('<info>  $> shared directories symlinks: </info><error>NOT OK</error>');
            exit(1); // bash code error
            // throw new \Exception('cannot create remote directories');
        }

        $output->writeln('<info>  $> shared directories symlinks: </info>OK');
    }
}
