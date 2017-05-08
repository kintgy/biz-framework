<?php

namespace Codeages\Biz\Framework\Provider;

use Codeages\Biz\Framework\Scheduler\Pool\JobPool;
use Codeages\Biz\Framework\Scheduler\Checker\CheckerChain;
use Codeages\Biz\Framework\Scheduler\Checker\MisFireChecker;
use Codeages\Biz\Framework\Scheduler\Scheduler;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class SchedulerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $biz)
    {
        $biz['migration.directories'][] = dirname(dirname(__DIR__)).'/migrations/scheduler';
        $biz['autoload.aliases']['Scheduler'] = 'Codeages\Biz\Framework\Scheduler';

        $biz['scheduler.job.pool.options'] = array(
            'maxNum'  => 10,
            'timeout' => 120,
        );

        $biz['scheduler.job.pool'] = function ($biz) {
            return new JobPool($biz);
        };

        $biz['scheduler.job.checker_chain'] = function ($biz) {
            return new CheckerChain($biz);
        };

        $biz['scheduler.job.checkers'] = array(
            new MisFireChecker()
        );

        $biz['scheduler'] = function ($biz){
            return new Scheduler($biz);
        };
    }
}