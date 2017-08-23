<?php

namespace Tests;

class SchedulerTest extends IntegrationTestCase
{
    /**
     * @expectedException \Exception
     */
    public function testCreateJobWithoutName()
    {
        $job = array(
            'source' => 'MAIN',
            'class' => 'Tests\\Example\\Job\\ExampleJob',
            'expression' => '0 17 * * *',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );

        $this->getSchedulerService()->register($job);
    }

    /**
     * @expectedException \Exception
     */
    public function testCreateJobWithoutExpression()
    {
        $job = array(
            'name' => 'test',
            'source' => 'MAIN',
            'class' => 'Tests\\Example\\Job\\ExampleJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );

        $this->getSchedulerService()->register($job);
    }

    /**
     * @expectedException \Exception
     */
    public function testCreateJobWithoutClass()
    {
        $job = array(
            'name' => 'test',
            'expression' => '0 17 * * *',
            'source' => 'MAIN',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );

        $this->getSchedulerService()->register($job);
    }

    public function testCreateJob()
    {
        $job = array(
            'name' => 'test',
            'source' => 'MAIN',
            'expression' => '0 17 * * *',
//            'nextFireTime' => time()-1,
            'class' => 'Tests\\Example\\Job\\ExampleJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );

        $savedJob = $this->getSchedulerService()->register($job);

        $this->asserts($job, $savedJob);
        $this->assertNotEmpty($savedJob['next_fire_time']);

        $logs = $this->getSchedulerService()->searchJobLogs(array(), array(), 0, 1);

        $excepted = array(
            'name' => 'test',
            'source' => 'MAIN',
            'class' => 'Tests\\Example\\Job\\ExampleJob',
            'args' => array('courseId' => 1),
            'status' => 'created',
        );
        foreach ($logs as $log) {
            $this->asserts($excepted, $log);
        }
    }

    public function testAfterNowRun()
    {
        $this->testCreateJob();
        $this->getSchedulerService()->execute();

        $second = time() % 60;
        if ($second > 57) {
            sleep(5);
        }

        $time = time() + 2;

        $job = array(
            'name' => 'test2',
            'source' => 'MAIN',
            'expression' => $time,
            'class' => 'Tests\\Example\\Job\\ExampleJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'executing',
        );

        $savedJob = $this->getSchedulerService()->register($job);
        $this->getSchedulerService()->execute();
        $this->assertEquals($time - $time % 60, $savedJob['next_fire_time']);

        $this->asserts($job, $savedJob);

        $jobFireds = $this->getSchedulerService()->findJobFiredsByJobId($savedJob['id']);
        $this->assertNotEmpty($jobFireds[0]);

        $jobFired = $jobFireds[0];
        $this->assertEquals('success', $jobFired['status']);

        $savedJob = $this->getJobDao()->get($savedJob['id']);
        $this->assertEquals(1, $savedJob['deleted']);
        $this->assertNotEmpty($savedJob['deleted_time']);
    }

    public function testBeforeNowRun()
    {
        $time = time() - 50000;

        $job = array(
            'name' => 'test2',
            'source' => 'MAIN',
            'expression' => $time,
            'class' => 'Tests\\Example\\Job\\ExampleJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'executing',
        );

        $savedJob = $this->getSchedulerService()->register($job);
        $this->getSchedulerService()->execute();
        $this->assertEquals($time - $time % 60, $savedJob['next_fire_time']);

        $this->asserts($job, $savedJob);

        $jobFireds = $this->getSchedulerService()->findJobFiredsByJobId($savedJob['id']);
        $this->assertNotEmpty($jobFireds[0]);

        $jobFired = $jobFireds[0];
        $this->assertEquals('success', $jobFired['status']);

        $savedJob = $this->getJobDao()->get($savedJob['id']);
        $this->assertEquals(1, $savedJob['deleted']);
        $this->assertNotEmpty($savedJob['deleted_time']);
    }

    public function testDeleteJobByName()
    {
        $job = array(
            'name' => 'test',
            'source' => 'MAIN',
            'expression' => '0 17 * * *',
//            'nextFireTime' => time()-1,
            'class' => 'Tests\\Example\\Job\\ExampleJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );

        $savedJob = $this->getSchedulerService()->register($job);
        $this->getSchedulerService()->deleteJobByName('test');
        $savedJob = $this->getJobDao()->get($savedJob['id']);

        $this->assertEquals(1, $savedJob['deleted']);
        $this->assertNotEmpty($savedJob['deleted_time']);
    }

    public function testFailJobResult()
    {
        $job = array(
            'name' => 'test',
            'source' => 'MAIN',
            'expression' => time()-2,
//            'nextFireTime' => time()-1,
            'class' => 'Tests\\Example\\Job\\ExampleFailJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );

        $job = $this->getSchedulerService()->register($job);
        $this->getSchedulerService()->execute();
        $savedJob = $this->getJobDao()->get($job['id']);
        $jobFireds = $this->getSchedulerService()->findJobFiredsByJobId($savedJob['id']);
        $this->assertEquals('failure', $jobFireds[0]['status']);
    }

    public function testAcquiredJobResult()
    {
        $job = array(
            'name' => 'test',
            'source' => 'MAIN',
            'expression' => time()-2,
//            'nextFireTime' => time()-1,
            'class' => 'Tests\\Example\\Job\\ExampleAcquiredJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );
        $job = $this->getSchedulerService()->register($job);
        $this->getSchedulerService()->execute();

        $savedJob = $this->getJobDao()->get($job['id']);
        $jobFireds = $this->getSchedulerService()->findJobFiredsByJobId($savedJob['id']);
        $this->assertEquals('acquired', $jobFireds[0]['status']);
    }

    public function testClearJobs()
    {
        $job = array(
            'name' => 'test',
            'source' => 'MAIN',
            'expression' => time()-2,
//            'nextFireTime' => time()-1,
            'class' => 'Tests\\Example\\Job\\ExampleAcquiredJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );
        $job = $this->getSchedulerService()->register($job);
        sleep(2);
        $this->getSchedulerService()->execute();

        $options = $this->biz['scheduler.options'];
        $options['timeout'] = 1;
        $this->biz['scheduler.options'] = $options;

        $this->getSchedulerService()->clearJobs();
        $job = $this->getJobDao()->get($job['id']);
        $this->assertEmpty($job);
    }

    public function testTimeoutJobs()
    {
        $job = array(
            'name' => 'test',
            'source' => 'MAIN',
            'expression' => time()-2,
//            'nextFireTime' => time()-1,
            'class' => 'Tests\\Example\\Job\\ExampleAcquiredJob',
            'args' => array('courseId' => 1),
            'priority' => 100,
            'misfire_threshold' => 3000,
            'misfire_policy' => 'missed',
        );
        $job = $this->getSchedulerService()->register($job);
        $this->getSchedulerService()->execute();
        $this->mockUnReleasePool($job);

        $options = $this->biz['scheduler.options'];
        $options['timeout'] = 1;
        $this->biz['scheduler.options'] = $options;

        $this->getSchedulerService()->markTimeoutJobs();
        $savedJob = $this->getJobDao()->get($job['id']);
        $jobFireds = $this->getSchedulerService()->findJobFiredsByJobId($savedJob['id']);
        $this->assertEquals('timeout', $jobFireds[0]['status']);
    }

    protected function wavePoolNum($id, $diff)
    {
        $ids = array($id);
        $diff = array('num' => $diff);
        $this->getJobPoolDao()->wave($ids, $diff);
    }

    protected function getJobPoolDao()
    {
        return $this->biz->dao('Scheduler:JobPoolDao');
    }

    protected function asserts($excepted, $acturel)
    {
        $keys = array_keys($excepted);
        foreach ($keys as $key) {
            if ('expression' == $key) {
                continue;
            }
            $this->assertEquals($excepted[$key], $acturel[$key]);
        }
    }

    protected function getJobDao()
    {
        return $this->biz->dao('Scheduler:JobDao');
    }

    protected function getJobFiredDao()
    {
        return $this->biz->dao('Scheduler:JobFiredDao');
    }

    protected function getSchedulerService()
    {
        return $this->biz->service('Scheduler:SchedulerService');
    }

    /**
     * @param $job
     */
    protected function mockUnReleasePool($job)
    {
        $this->getJobFiredDao()->update(array('job_id' => $job['id']), array(
            'status' => 'executing',
            'fired_time' => time() - 2
        ));

        $jobPool = $this->getJobPoolDao()->getByName($job['pool']);
        $this->wavePoolNum($jobPool['id'], 1);
    }
}
