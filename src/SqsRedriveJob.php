<?php

namespace SumaiaZaman\SqsRedrive;

use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\SqsJob;

class SqsRedriveJob extends SqsJob
{
    /**
     * The SQS queue's redrive policy.
     *
     * @var array|null
     */
    protected ?array $redrivePolicy;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  \Aws\Sqs\SqsClient  $sqs
     * @param  array  $job
     * @param  string  $connectionName
     * @param  string  $queue
     * @param  array|null  $redrivePolicy
     */
    public function __construct(
        Container $container,
        SqsClient $sqs,
        array $job,
        string $connectionName,
        string $queue,
        ?array $redrivePolicy = null,
    ) {
        parent::__construct($container, $sqs, $job, $connectionName, $queue);

        $this->redrivePolicy = $redrivePolicy;
    }

    /**
     * Delete the job from the queue.
     *
     * When a redrive policy is configured and the job has failed, skip the
     * SQS deleteMessage call so SQS can natively route the message to the
     * dead letter queue after maxReceiveCount attempts.
     *
     * @return void
     */
    public function delete()
    {
        if ($this->hasFailed() && $this->redrivePolicy !== null) {
            // Mark as deleted in Laravel without calling SQS deleteMessage.
            // This lets SQS increment the receive count and eventually
            // move the message to the dead letter queue.
            $this->deleted = true;

            return;
        }

        parent::delete();
    }

    /**
     * Get the number of times the job may be attempted.
     *
     * When a redrive policy is configured, use the maxReceiveCount from the
     * policy so Laravel's retry count stays in sync with SQS.
     *
     * @return int|null
     */
    public function maxTries()
    {
        if ($this->redrivePolicy !== null) {
            return (int) $this->redrivePolicy['maxReceiveCount'];
        }

        return parent::maxTries();
    }

    /**
     * Get the redrive policy for this job's queue.
     *
     * @return array|null
     */
    public function getRedrivePolicy(): ?array
    {
        return $this->redrivePolicy;
    }

    /**
     * Determine if the queue has a dead letter queue configured.
     *
     * @return bool
     */
    public function hasDeadLetterQueue(): bool
    {
        return $this->redrivePolicy !== null;
    }
}
