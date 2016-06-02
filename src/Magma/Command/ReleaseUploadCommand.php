<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;

use Magma\Common\Config;
use Magma\Common\CmdBuilder;
use Magma\Common\PathBuilder;

class ReleaseUploadCommand extends Command
{
    /**
     * Configure command.
     *
     * @return [type] [description]
     */
    protected function configure()
    {
        $this
            ->setName('release:upload')
            ->setDescription('Upload release files to remote (release has to be setup first)')
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
        $release = $input->getArgument('release');

        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));
        $shared = $config->getParameter('project.shared');

        $releaseDirPath = $path.'/releases/'.$release;
        $sharedDirsPath = PathBuilder::create($path.'/shared/', $shared);

        // get dirs to exclude

        // prepare the exclude list for rsync
        // exclude list:
        //      excluded directories from config
        //      shared directories

        //$command = CmdBuilder::ssh($user, $host, $commands);
        $rsyncOptions = array(
            'exclude' => $config->getParameter('project.exclude'),
            'delete-excluded' => CmdBuilder::RSYNC_DELETE_EXCLUDED
        );
        
        $command = CmdBuilder::rsync($user, $host, $releaseDirPath, $rsyncOptions);

        // execute process via ssh
        $this->runProcess($input, $output, $command, $release);
    }

    /**
     * [runProcess description]
     * @param  InputInterface  $input   [description]
     * @param  OutputInterface $output  [description]
     * @param  [type]          $command [description]
     * @return [type]                   [description]
     */
    protected function runProcess(InputInterface $input, OutputInterface $output, $command, $release)
    {
        $clocks = array(
            "\xF0\x9F\x95\x92",
            "\xF0\x9F\x95\x93",
            "\xF0\x9F\x95\x9D",
            "\xF0\x9F\x95\x9F",
        );

        $process = new Process($command);
        $process->setTimeout(null);

        $progress = new ProgressBar($output);
        $progress->setFormat('  [%bar%] %message%');
        $progress->setMessage($clocks[0].'  starting...');

        $output->writeln(sprintf('<info>  $> uploading release <bg=yellow;options=bold>%s</>...</info>', $release));

        // show progress while uploading
        $progress->start();
        $process->run(function ($type, $buffer) use ($progress, $clocks) {
            $randKey = array_rand($clocks);
            $clock = $clocks[$randKey];

            $progress->setMessage($clock.'  uploading...');
            $progress->advance();
        });

        $progress->setMessage($clocks[0].'  finished');
        $progress->finish();
        $output->writeln('');

        if (!$process->isSuccessful()) {
            // quit on error
            $output->writeln(sprintf('<info>  $> release upload: </info><error>NOT OK</error>', $release));
            exit(1); // bash code error
        }

        $output->writeln(sprintf('<info>  $> release upload: </info>OK', $release));

        // render full stack if verbosity level is a bit higher
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $stats = $this->getRsyncStatsOutput($process->getOutput());

            $table = new Table($output);
            $table
                ->setHeaders(array('Info', 'Stats'))
                ->setRows(array(
                    array('total files considered', $stats['total']),
                    array('total files transfered', $stats['transfered']),
                ))
            ;
            $table->render();
        }
    }

    /**
     * Gets rsync putput with the "--stats" options.
     *
     * @param  [string] Process output
     * @return [array]  Stats
     */
    private function getRsyncStatsOutput($buffer)
    {
        $total = null;
        $transfered = null;

        if (preg_match('~Number of files: ([0-9]+)~', $buffer, $matches)) {
            $total = $matches[1];
        }

        if (preg_match('~Number of files transferred: ([0-9]+)~', $buffer, $matches)) {
            $transfered = $matches[1];
        }

        return array(
            'total' => $total,
            'transfered' => $transfered,
        );
    }
}
