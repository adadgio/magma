<?php

namespace Magma\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
// use Symfony\Component\Console\Input\InputOption;
// use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\Table;

use Magma\Common\Config;
use Magma\Common\CmdBuilder;
use Magma\Common\RsyncOutput;

class ConfigTestCommand extends Command
{
    /**
     * Configure command.
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('configtest')
            ->setDescription('Test remote and local config')
        ;
    }

    /**
     * Execute command.
     * @param  InputInterface  $input  [description]
     * @param  OutputInterface $output [description]
     * @return [type]                  [description]
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = new Config();

        $rows = array();
        $table = new Table($output);
        $table->setHeaders(array('Config', 'Value', 'Status'));

        foreach ($this->getConfigChecks($config) as $key => $check) {
            $status = ($check['status'] === true) ? '<fg=green>Ok</>' : '<fg=red>Not Ok</>';
            $rows[] = array($key, $check['value'], $status);
        }

        $table->setRows($rows);
        $table->render();
    }

    /**
     * Get config checks.
     * @param \Config
     * @return array
     */
    private function getConfigChecks(Config $config)
    {
        $checks = array('Project name' => array(), 'Environments' => array());

        // perform all checks
        $checks['Project name']['value'] = $config->getParameter('project.name');
        $checks['Project name']['status'] = empty($checks['Project name']['value']) ? false : true;

        $checks['Environments']['value'] = implode(',', array_keys($config->getParameter('project.environments')));
        $checks['Environments']['status'] = empty($checks['Environments']['value']) ? false : true;


        $envs = $config->getParameter('project.environments');
        foreach ($envs as $env => $conf) {
            $ip = $conf['remote']['host'];

            $itemKey = ucfirst($env).' environment';
            $checks[$itemKey]['value'] = 'Ping on <fg=yellow>'.$ip.'</>';
            $checks[$itemKey]['status'] = $this->ping($ip);
        }

        return $checks;
    }

    private function ping($remote)
    {
        if (null === $remote) { return false; }

        $ch = curl_init($remote);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200) ? true : false;
    }
}
