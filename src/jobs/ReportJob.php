<?php

namespace astuteo\astuteopulse;

use Craft;
use craft\queue\BaseJob;

use astuteo\astuteopulse\services\ReportStatusService;

/**
 * Job to make the request to the Airtable inventory
 */
class ReportJob extends BaseJob
{
    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        ReportStatusService::makeRequest();
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription()
    {
        return 'Send Heartbeat';
    }
}