<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\ProgressBar;

class DeployCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('deploy')
            ->setDescription('Deploys project to server')
            // ->addOption(
            //     'package', null, InputOption::VALUE_OPTIONAL, 'The app package name identifier'
            // )
            // ->setDefinition(array(
            //     new InputOption('start', 's', InputOption::VALUE_OPTIONAL, 'Starts a process', 0)
            // ))
            ->setHelp('Deploys project to server using local configuration file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $localDir = getcwd();
        
        // setup connection
        $transfer = new AppTransfer($localDir, AppTransfer::CERT_VERIFY);
        $remoteDir = Path::REMOTE_BASE_DIR.Path::DIRECTORY_SEPARATOR.$transfer->getPackage();

        $transfer
            ->setRemoteDir($remoteDir)
            ->login(ServerAuth::username(), ServerAuth::password())
            ->checkAuthenticity();

        $localFiles = $transfer->findLocalFiles();

        // init progress bar
        $progress = new ProgressBar($output, count($localFiles));
        $progress->setFormat('verbose');
        $progress->start();

        // transfer all files
        foreach ($localFiles as $localFilepath) {
            $remoteFilepath = $transfer->getRemoteDir().'/'.basename($localFilepath);
            $transfer->transfer($localFilepath, $remoteFilepath);
            $progress->advance();
        }

        // close connection
        $progress->finish();
        $transfer->close();
        $output->writeln('');
        $output->writeln('');

        if ($transfer->getErrors()) {
            foreach ($transfer->getErrors() as $error) {
                $output->writeln(sprintf('<error>%s</error>', $error));
            }
        } else {
            $output->writeln(sprintf('<info>All app files (%d) transfered</info>', count($localFiles)));
        }


    }
}
