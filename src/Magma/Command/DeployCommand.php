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

class DeployCommand extends Command
{
    const SPACE = "  ";

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
        $config = new Config();
        $helper = $this->getHelper('question');

        // read the config to find available environments
        $environments = array_keys($config->getParameter('project.environments'));
        $question = new Question(sprintf('<info>$> Choose your deploy environment target [%s]?</info> ', $environments[0]), $environments[0]);

        $env = $helper->ask($input, $output, $question);
        if (!in_array($env, $environments)) {
            throw new \Exception(sprintf('Environment "%s" does not exist', $env));
        }

        $cmd = CmdBuilder::rsync($config, $env);
        $process = new Process($cmd);
        $progress = new ProgressBar($output);
        //$process->disableOutput();

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

            if ($this->toConsider($buffer)) {
                $nbrFiles = $this->toConsider($buffer);
                $progress->setMessage($clock.sprintf('%s file(s) to consider', $nbrFiles));
                $progress->advance();
            }

            if ($this->readToCheck($buffer)) {
                $toCheck = $this->readToCheck($buffer);
                //print_r($toCheck);
                // $output->writeln(sprintf(static::SPACE.'<info>$ transfering 1 file, %d files remaining</info>', $toCheck['to_check']));
                $progress->setMessage($clock.sprintf('%s file(s) on %d to check, %d total', $toCheck['nth_transfer'], $toCheck['to_check'], $toCheck['total']));
                $progress->advance();
            }

            if ($this->inTranser($buffer)) {
                $inTransfer = $this->inTranser($buffer);
                $progress->setMessage($clock.sprintf('<info>transfering</info> %s', $inTransfer));
                $progress->advance();
            }

            if ($this->isUptodate($buffer)) {
                $isUptodate = $this->isUptodate($buffer);
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
        $output->writeln('<info>$> project successfully deployed.</info>');
        $output->writeln('');
    }

    /**
     * [readToCheck description]
     * @param  [type] $string [description]
     * @return [type]         [description]
     */
    private function readToCheck($string)
    {
        if (preg_match('~(xfer#([0-9]+), to-check=([0-9]+)/([0-9]+))~', $string, $matches)) {
            return array(
                'nth_transfer'  => $matches[2],
                'to_check'      => $matches[3],
                'total'         => $matches[4],
            );
        } else {
            return false;
        }
    }

    /**
     * [inTranser description]
     * @param  [type] $string [description]
     * @return [type]         [description]
     */
    public function inTranser($string)
    {
        if (preg_match('~^([a-z0-9\/\.]+)$~i', trim($string), $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * [toConsider description]
     * @param  [type] $string [description]
     * @return [type]         [description]
     */
    private function toConsider($string)
    {
        if (preg_match('~([0-9]+) files to consider~', $string, $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * [readUptodate description]
     * @return [type] [description]
     */
    private function isUptodate($string)
    {
        if (preg_match('~(.*) is uptodate~', $string, $matches)) {
            return $matches[1];
        } else {
            return false;
        }

    }
}
