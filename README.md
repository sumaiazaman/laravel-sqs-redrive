# Laravel SQS Redrive

Native AWS SQS dead letter queue (DLQ) support for Laravel.

When an SQS queue has a [redrive policy](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-dead-letter-queues.html) configured, this package automatically:

- **Detects the redrive policy** from SQS and caches it per queue URL
- **Aligns Laravel's retry count** with SQS `maxReceiveCount`
- **Skips message deletion on failure** so SQS natively routes failed messages to the dead letter queue

## The Problem

Laravel's default SQS driver deletes messages from SQS immediately after a job fails. This bypasses SQS's native dead letter queue routing entirely — failed messages never reach your DLQ.

```
Job fails → Laravel calls deleteMessage → Message gone → DLQ never receives it
```

## The Solution

This package lets SQS handle the routing:

```
Job fails → Message stays in queue → SQS increments receive count → After maxReceiveCount, SQS moves to DLQ
```

## Installation

```bash
composer require sumaiazaman/laravel-sqs-redrive
```

The package auto-discovers the service provider.

## Configuration

In your `config/queue.php`, add a new connection using the `sqs-redrive` driver:

```php
'connections' => [

    'sqs-redrive' => [
        'driver' => 'sqs-redrive',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
        'queue' => env('SQS_QUEUE', 'default'),
        'suffix' => env('SQS_SUFFIX'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

],
```

Then set your default queue connection:

```env
QUEUE_CONNECTION=sqs-redrive
```

That's it. No other configuration needed — the package reads the redrive policy directly from SQS.

## How It Works

### 1. Automatic Redrive Policy Detection

When a job is popped from the queue, the package fetches and caches the queue's redrive policy via the SQS `getQueueAttributes` API. The result is cached for the lifetime of the worker process.

### 2. Retry Count Alignment

If a redrive policy exists, `maxTries()` returns `maxReceiveCount` from the policy. This keeps Laravel's retry logic in sync with SQS — no more conflicts between Laravel's `$tries` and SQS's receive count.

### 3. Smart Deletion

- **Successful jobs**: Deleted from SQS normally
- **Failed jobs without DLQ**: Deleted from SQS normally (same as default behavior)
- **Failed jobs with DLQ**: Skipped deletion — SQS handles routing to DLQ

## API

The `SqsRedriveJob` class provides additional methods:

```php
// Check if the queue has a dead letter queue configured
$job->hasDeadLetterQueue(); // bool

// Get the full redrive policy
$job->getRedrivePolicy(); // ['maxReceiveCount' => 3, 'deadLetterTargetArn' => '...']

// maxTries() automatically uses maxReceiveCount when DLQ is configured
$job->maxTries(); // 3
```

## Requirements

- PHP 8.2+
- Laravel 12.x or 13.x
- AWS SQS with a redrive policy configured

## Testing

```bash
composer test
```

## License

MIT
