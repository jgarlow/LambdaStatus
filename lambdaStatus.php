<?php

require 'vendor/autoload.php';

use Aws\Lambda\LambdaClient;
use Aws\CloudWatch\CloudWatchClient;
use Aws\Exception\AwsException;

function getMetricStatistics(CloudWatchClient $cloudWatchClient, $namespace, $metricName,
    $dimensions, $startTime, $endTime, $period, $statistics, $unit)
{
    try {
        $result = $cloudWatchClient->getMetricStatistics([
            'Namespace' => $namespace,
            'MetricName' => $metricName,
            'Dimensions' => $dimensions,
            'StartTime' => $startTime,
            'EndTime' => $endTime,
            'Period' => $period,
            'Statistics' => $statistics,
            'Unit' => $unit
        ]);

        $maxConcurrent = 0;
        $datapoints =  $result->get('Datapoints');
        foreach ($datapoints as $datapoint) {
            if ($datapoint['Maximum'] > $maxConcurrent) $maxConcurrent = $datapoint['Maximum'];
        }
        return $maxConcurrent;
    } catch (AwsException $e) {
        return 'Error: ' . $e->getAwsErrorMessage();
    }
}

$client = LambdaClient::factory(array(
    'version' => 'latest',
    'profile' => 'default',
    'region'  => 'us-east-1'
));

$metricsClient = new CloudWatchClient(array(
    'version' => 'latest',
    'profile' => 'default',
    'region'  => 'us-east-1'
));

$result = $client->listFunctions();

echo "<table>";

foreach ($result['Functions'] as $lambdaFunction) {
    echo '<tr><td colspan="1">' . $lambdaFunction['FunctionName'] . '</td>';
    $arn = $lambdaFunction['FunctionArn'];

    $namespace = "AWS/Lambda";
    $metricName = "ConcurrentExecutions";
    $dimensions = [
        [
            'Name' => 'FunctionName',
            'Value' => $lambdaFunction['FunctionName']
        ],
    ];

    $startTime = strtotime('-7 days');
    $endTime = strtotime('now');
    $period = 86400; // Seconds. (1 day = 86400 seconds.)
    $statistics = array('Maximum');
    $unit = 'Count';
    $maxConcurrent = getMetricStatistics($metricsClient, $namespace, $metricName,
        $dimensions, $startTime, $endTime, $period, $statistics, $unit);

    echo '<td colspan="1">' . $maxConcurrent . '</td>';


    $concurrency =  $client->getFunctionConcurrency([
        'FunctionName' => $arn, // REQUIRED
    ]);
    echo '<td colspan="1">' . $concurrency['ReservedConcurrentExecutions'] . '</td>';
    echo '<td colspan="1">' . $lambdaFunction['MemorySize'] . '</td>';
    echo '<td colspan="1">' . $lambdaFunction['Runtime'] . '</td>';
    echo '<td colspan="1">Deployed</td>';

    echo "</tr> \n ";

};

echo "</table>";
