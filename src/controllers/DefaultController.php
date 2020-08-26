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

use astuteo\astuteopulse\AstuteoPulse;
use astuteo\astuteopulse\services\BroadcastStatusService;

use Craft;
use craft\web\Controller;

/**
 * Default Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Astuteo
 * @package   PulseReceiver
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'do-something'];

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/pulse-receiver/default
     *
     */
    public function actionIndex()
    {

        $result = BroadcastStatusService::broadcastInfo();
        return $result;
    }

}