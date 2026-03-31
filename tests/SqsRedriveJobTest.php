<?php

namespace SumaiaZaman\SqsRedrive\Tests;

use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use SumaiaZaman\SqsRedrive\SqsRedriveJob;

class SqsRedriveJobTest extends TestCase
{
    protected $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789/emails';
    protected $receiptHandle = '0NNAq8PwvXuWv5gMtS9DJ8qEdyiUwbAjpp45w2m6M4SJ';

    protected function tearDown(): void
    {
        m::close();
    }

    public function testDeleteRemovesJobFromSqsWhenNoRedrivePolicy()
    {
        $job = $this->makeJob(redrivePolicy: null);

        $job->getSqs()->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->receiptHandle,
        ]);

        $job->delete();
    }

    public function testDeleteRemovesNonFailedJobEvenWithRedrivePolicy()
    {
        $job = $this->makeJob(redrivePolicy: $this->redrivePolicy());

        $job->getSqs()->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->receiptHandle,
        ]);

        $job->delete();
    }

    public function testDeleteSkipsSqsDeletionWhenFailedJobHasRedrivePolicy()
    {
        $job = $this->makeJob(redrivePolicy: $this->redrivePolicy());

        $job->markAsFailed();

        $job->getSqs()->shouldNotReceive('deleteMessage');

        $job->delete();
        $this->assertTrue($job->isDeleted());
    }

    public function testDeleteRemovesFailedJobFromSqsWhenNoRedrivePolicy()
    {
        $job = $this->makeJob(redrivePolicy: null);

        $job->markAsFailed();

        $job->getSqs()->shouldReceive('deleteMessage')->once();

        $job->delete();
    }

    public function testMaxTriesReturnsRedriveMaxReceiveCount()
    {
        $job = $this->makeJob(redrivePolicy: $this->redrivePolicy(maxReceiveCount: 5));

        $this->assertSame(5, $job->maxTries());
    }

    public function testMaxTriesFallsBackToPayloadWithoutRedrivePolicy()
    {
        $job = $this->makeJob(redrivePolicy: null);

        $this->assertNull($job->maxTries());
    }

    public function testHasDeadLetterQueue()
    {
        $withDlq = $this->makeJob(redrivePolicy: $this->redrivePolicy());
        $withoutDlq = $this->makeJob(redrivePolicy: null);

        $this->assertTrue($withDlq->hasDeadLetterQueue());
        $this->assertFalse($withoutDlq->hasDeadLetterQueue());
    }

    public function testGetRedrivePolicy()
    {
        $policy = $this->redrivePolicy(maxReceiveCount: 3);
        $job = $this->makeJob(redrivePolicy: $policy);

        $this->assertSame($policy, $job->getRedrivePolicy());
        $this->assertSame(3, $job->getRedrivePolicy()['maxReceiveCount']);
    }

    protected function makeJob(?array $redrivePolicy): SqsRedriveJob
    {
        return new SqsRedriveJob(
            m::mock(Container::class),
            m::mock(SqsClient::class)->makePartial(),
            [
                'Body' => json_encode(['job' => 'test', 'data' => [], 'attempts' => 1]),
                'MD5OfBody' => md5('test'),
                'ReceiptHandle' => $this->receiptHandle,
                'MessageId' => 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81',
                'Attributes' => ['ApproximateReceiveCount' => 1],
            ],
            'sqs-redrive',
            $this->queueUrl,
            $redrivePolicy,
        );
    }

    protected function redrivePolicy(int $maxReceiveCount = 3): array
    {
        return [
            'maxReceiveCount' => $maxReceiveCount,
            'deadLetterTargetArn' => 'arn:aws:sqs:us-east-1:123456789:emails-dlq',
        ];
    }
}
