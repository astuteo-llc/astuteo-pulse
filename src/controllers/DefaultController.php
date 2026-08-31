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
        $this->uncacheableResponse();

        return BroadcastStatusService::broadcastInfo();
    }

    public function actionJson(): \yii\web\Response
    {
        $response = $this->uncacheableResponse();
        $response->format = \yii\web\Response::FORMAT_JSON;
        $response->data = json_decode(BroadcastStatusService::broadcastInfo(), true);
        return $response;
    }

    /**
     * The credential is a header, so the URL no longer varies by caller and a shared cache
     * could otherwise serve an authorized report to an unauthenticated request.
     */
    private function uncacheableResponse(): Response
    {
        $response = Craft::$app->getResponse();
        $response->setNoCacheHeaders();
        $response->getHeaders()->set('Vary', BroadcastStatusService::CREDENTIAL_HEADER);

        return $response;
    }

}
