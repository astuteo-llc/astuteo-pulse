<?php
/**
 * Pulse Receiver plugin for Craft CMS 3.x
 *
 * Internal
 *
 * @link      astuteo.com
 * @copyright Copyright (c) 2020 Astuteo
 */

namespace astuteo\astuteopulse\controllers;

use astuteo\astuteopulse\services\BroadcastStatusService;


use Craft;
use craft\web\Controller;
use craft\web\Response;


class DefaultController extends Controller
{
    protected array|int|bool $allowAnonymous = ['index', 'json'];

    /**
     * Handles the index action
     * 
     * @return \yii\web\Response
     */
    public function actionIndex(): bool|string
    {
        return BroadcastStatusService::broadcastInfo();
    }

    public function actionJson(): \yii\web\Response
    {
        $response = Craft::$app->getResponse();
        $response->format = \yii\web\Response::FORMAT_JSON;
        $response->data = json_decode(BroadcastStatusService::broadcastInfo(), true);
        return $response;
    }

}
