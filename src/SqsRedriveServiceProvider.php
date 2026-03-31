<?php

namespace SumaiaZaman\SqsRedrive;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

class SqsRedriveServiceProvider extends ServiceProvider
{
    /**
     * Register the SQS redrive queue connector.
     */
    public function register(): void
    {
        $this->app->afterResolving(QueueManager::class, function (QueueManager $manager) {
            $manager->addConnector('sqs-redrive', function () {
                return new SqsRedriveConnector;
            });
        });
    }
}
