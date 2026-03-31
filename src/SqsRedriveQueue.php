<?php

namespace SumaiaZaman\SqsRedrive;

use Illuminate\Queue\SqsQueue;

class SqsRedriveQueue extends SqsQueue
{
    /**
     * The cached redrive policies for each queue URL.
     *
     * @var array<string, array|null>
     */
    protected array $redrivePolicies = [];

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (! is_null($response['Messages']) && count($response['Messages']) > 0) {
            return new SqsRedriveJob(
                $this->container,
                $this->sqs,
                $response['Messages'][0],
                $this->connectionName,
                $queue,
                $this->redrivePolicy($queue),
            );
        }

        return null;
    }

    /**
     * Get the redrive policy for the given queue.
     *
     * @param  string|null  $queue
     * @return array|null
     */
    public function redrivePolicy($queue = null): ?array
    {
        $queueUrl = $this->getQueue($queue);

        if (! array_key_exists($queueUrl, $this->redrivePolicies)) {
            $response = $this->sqs->getQueueAttributes([
                'QueueUrl' => $queueUrl,
                'AttributeNames' => ['RedrivePolicy'],
            ]);

            $this->redrivePolicies[$queueUrl] = isset($response['Attributes']['RedrivePolicy'])
                ? json_decode($response['Attributes']['RedrivePolicy'], true)
                : null;
        }

        return $this->redrivePolicies[$queueUrl];
    }
}
