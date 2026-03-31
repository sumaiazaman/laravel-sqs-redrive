<?php

namespace SumaiaZaman\SqsRedrive\Tests;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use SumaiaZaman\SqsRedrive\SqsRedriveJob;
use SumaiaZaman\SqsRedrive\SqsRedriveQueue;

class SqsRedriveQueueTest extends TestCase
{
    protected $sqs;
    protected $account = '1234567891011';
    protected $queueName = 'emails';
    protected $prefix;
    protected $queueUrl;

    protected function setUp(): void
    {
        $this->sqs = m::mock(SqsClient::class);
        $this->prefix = 'https://sqs.us-east-1.amazonaws.com/'.$this->account.'/';
        $this->queueUrl = $this->prefix.$this->queueName;
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testRedrivePolicyIsFetchedAndCached()
    {
        $redrivePolicy = json_encode([
            'maxReceiveCount' => 5,
            'deadLetterTargetArn' => 'arn:aws:sqs:us-east-1:123456789:dlq',
        ]);

        $this->sqs->shouldReceive('getQueueAttributes')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'AttributeNames' => ['RedrivePolicy'],
        ])->andReturn(new Result([
            'Attributes' => ['RedrivePolicy' => $redrivePolicy],
        ]));

        $queue = new SqsRedriveQueue($this->sqs, $this->queueName, $this->prefix);

        $result = $queue->redrivePolicy($this->queueName);
        $this->assertEquals(5, $result['maxReceiveCount']);

        // Second call should use cache — getQueueAttributes called only once.
        $cached = $queue->redrivePolicy($this->queueName);
        $this->assertEquals($result, $cached);
    }

    public function testRedrivePolicyReturnsNullWhenNotConfigured()
    {
        $this->sqs->shouldReceive('getQueueAttributes')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'AttributeNames' => ['RedrivePolicy'],
        ])->andReturn(new Result([
            'Attributes' => [],
        ]));

        $queue = new SqsRedriveQueue($this->sqs, $this->queueName, $this->prefix);

        $this->assertNull($queue->redrivePolicy($this->queueName));
    }

    public function testPopReturnsSqsRedriveJobWithPolicy()
    {
        $redrivePolicy = json_encode([
            'maxReceiveCount' => 3,
            'deadLetterTargetArn' => 'arn:aws:sqs:us-east-1:123456789:dlq',
        ]);

        $this->sqs->shouldReceive('receiveMessage')->once()->andReturn(new Result([
            'Messages' => [[
                'Body' => json_encode(['job' => 'test', 'data' => []]),
                'MD5OfBody' => md5('test'),
                'ReceiptHandle' => 'receipt-handle',
                'MessageId' => 'msg-id',
                'Attributes' => ['ApproximateReceiveCount' => 1],
            ]],
        ]));

        $this->sqs->shouldReceive('getQueueAttributes')->once()->andReturn(new Result([
            'Attributes' => ['RedrivePolicy' => $redrivePolicy],
        ]));

        $queue = new SqsRedriveQueue($this->sqs, $this->queueName, $this->prefix);
        $queue->setContainer(m::mock(Container::class));

        $job = $queue->pop();

        $this->assertInstanceOf(SqsRedriveJob::class, $job);
        $this->assertTrue($job->hasDeadLetterQueue());
        $this->assertSame(3, $job->maxTries());
    }

    public function testPopReturnsNullWhenNoMessages()
    {
        $this->sqs->shouldReceive('receiveMessage')->once()->andReturn(new Result([
            'Messages' => null,
        ]));

        $queue = new SqsRedriveQueue($this->sqs, $this->queueName, $this->prefix);

        $this->assertNull($queue->pop());
    }
}
