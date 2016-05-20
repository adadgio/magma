<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
// use Symfony\Component\Console\Input\InputOption;
// use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;

use Magma\Common\Config;
use Magma\Common\CmdBuilder;
use Magma\Common\RsyncOutput;

class DeployCommand extends Command
{
    const SPACE = "  ";

    /**
     * Configure command.
     *
     * @return [type] [description]
     */
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

    /**
     * Execute command.
     *
     * @param  InputInterface  $input  [description]
     * @param  OutputInterface $output [description]
     * @return [type]                  [description]
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $release = time();
        $config = new Config();
        $helper = $this->getHelper('question');

        // read the config to find available environments
        $environments = array_keys($config->getParameter('project.environments'));
        $question = new Question(sprintf('<info>$> Choose your deploy environment target [%s]?</info> ', $environments[0]), $environments[0]);

        $env = $helper->ask($input, $output, $question);
        if (!in_array($env, $environments)) {
            throw new \Exception(sprintf('Environment "%s" does not exist', $env));
        }

        // prepare remote project folders
        $this->prepareReleaseDirectories($input, $output, $env, $release);

        // rsync to remote directory (latest releasr)
        $this->rsyncToRemote($input, $output, $env, $release);

        // setup shared directories symlink from the release to shared dirs
        $this->setupShared($input, $output, $env, $release);

        // execute post deploy tasks
        $this->postDeploy($input, $output, $env, $release);

        // deploy with symlink to latest release
        $this->deployWithSymlink($input, $output, $env, $release);
    }

    public function setupShared($input, $output, $env, $release)
    {
        $config = new Config();
        $cmd = CmdBuilder::sharedDirs($config, $env, $release);

        if (false === $cmd) {
            return true;
        }

        $process = new Process($cmd);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln(sprintf('<error>%s</error>', $process->getOutput()));
            throw new \Exception('could not setup shared directories');
        }

        return true;
    }

    /**
     * [postDeploy description]
     * @param  [type] $input   [description]
     * @param  [type] $output  [description]
     * @param  [type] $env     [description]
     * @param  [type] $release [description]
     * @return [type]          [description]
     */
    public function postDeploy($input, $output, $env, $release)
    {
        $config = new Config();
        $cmd = CmdBuilder::postDeploy($config, $env, $release);

        // no remote tasks to execute
        if (false === $cmd) {
            return true;
        }

        $process = new Process($cmd);
        $process->run();

        $output->writeln('<info>$> executing post deploy tasks...</info>');
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln(sprintf('<error>%s</error>', $process->getOutput()));
            throw new \Exception('could not execute some post deploy tasks');
        }

        return true;
    }

    /**
     * Deploy by creating a symlink to latest release.
     *
     * @param  [type] $input   [description]
     * @param  [type] $output  [description]
     * @param  [type] $env     [description]
     * @param  [type] $release [description]
     * @return [type]          [description]
     */
    public function deployWithSymlink($input, $output, $env, $release)
    {
        $config = new Config();
        $cmd = CmdBuilder::releaseSymlink($config, $env, $release);

        $process = new Process($cmd);
        $process->run();

        $output->writeln('<info>$> deploying with symlink to latest release...</info>');

        if (!$process->isSuccessful()) {
            throw new \Exception('could not deploy with symlink');
        }

        return true;
    }

    /**
     * Prepares remote directores structure.
     *
     * @param  InputInterface  $input   [description]
     * @param  OutputInterface $output  [description]
     * @param  [type]          $env     [description]
     * @param  [type]          $release [description]
     * @return [type]                   [description]
     */
    private function prepareReleaseDirectories(InputInterface $input, OutputInterface $output, $env, $release)
    {
        $config = new Config();
        $cmd = CmdBuilder::remoteDirs($config, $env, $release);

        $process = new Process($cmd);
        $process->run();

        $output->writeln('<info>$> preparing remote folders...</info>');
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception('could not prepare remote folders');
        }

        return true;
    }

    /**
     * Rsync all files that have changed to remote.
     *
     * @param  [type] $input   [description]
     * @param  [type] $output  [description]
     * @param  [type] $env     [description]
     * @param  [type] $release [description]
     * @return [type]          [description]
     */
    private function rsyncToRemote($input, $output, $env, $release)
    {
        $config = new Config();
        $cmd = CmdBuilder::rsync($config, $env, $release);

        $process = new Process($cmd);
        $progress = new ProgressBar($output);

        $clocks = array(
            "\xF0\x9F\x95\x92",
            "\xF0\x9F\x95\x93",
            "\xF0\x9F\x95\x9D",
            "\xF0\x9F\x95\x9F",
        );

        $output->writeln('<info>$> Deploying project...</info>');

        // $progress->setMessage('Task starts');
        $progress->setFormat(' %current%/%max% [%bar%] %message%');
        $progress->setMessage($clocks[0].static::SPACE.'starting...');
        $progress->start();

        $process->run(function ($type, $buffer) use ($output, $progress, $clocks) {

            $randKey = array_rand($clocks);
            $clock = $clocks[$randKey].static::SPACE;

            if (RsyncOutput::toConsider($buffer)) {
                $nbrFiles = RsyncOutput::toConsider($buffer);

                $progress->setMessage($clock.sprintf('%s file(s) to consider', $nbrFiles));
                $progress->advance();
            }

            if (RsyncOutput::toCheck($buffer)) {
                $toCheck = RsyncOutput::toCheck($buffer);

                $progress->setMessage($clock.sprintf('%s file(s) on %d to check, %d total', $toCheck['nth_transfer'], $toCheck['to_check'], $toCheck['total']));
                $progress->advance();
            }

            if (RsyncOutput::inTranser($buffer)) {
                $inTransfer = RsyncOutput::inTranser($buffer);

                $progress->setMessage($clock.sprintf('<info>transfering</info> %s', $inTransfer));
                $progress->advance();
            }

            if (RsyncOutput::upToDate($buffer)) {
                $isUptodate = RsyncOutput::upToDate($buffer);

                $progress->setMessage($clock.sprintf('<info>%s</info> is up top date', $isUptodate));
                $progress->advance();
            }

                if (Process::ERR === $type) {
                    $output->writeln(sprintf(static::SPACE.'<error>$ %s</error>', $buffer));
                    return;
                } else {
                    // already taken care of
                }
        });

        // run post deploy tasks
        $progress->setMessage('finished '."\xF0\x9F\x8D\xBA");
        $progress->finish();

        $output->writeln('');
        $output->writeln('<info>$> project files transfered</info>');

        return true;
    }
}
