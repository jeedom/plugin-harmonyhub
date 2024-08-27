<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
try {
    require_once __DIR__ . "/../../../../core/php/core.inc.php";

    if (!jeedom::apiAccess(init('apikey'), 'harmonyhub')) {
        echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
        die();
    }

    if (init('test') != '') {
        echo 'OK';
        log::add('harmonyhub', 'debug', 'test from daemon');
        die();
    }
    $messages = json_decode(file_get_contents("php://input"), true);
    if (!is_array($messages)) {
        die();
    }
    log::add('harmonyhub', 'debug', "new messages from daemon:" . json_encode($messages));
    foreach ($messages as $key => $data) {
        switch ($key) {
            case 'hubs':
                harmonyhub::createHubs($data);
                break;
            case 'starting_activity':
            case 'current_activity':
                foreach ($data as $hubId => $activity) {
                    /** @var harmonyhub */
                    $hub = eqLogic::byLogicalId($hubId, 'harmonyhub');
                    if (!is_object($hub)) {
                        log::add('harmonyhub', 'warning', __("Hub ID inconnu {$hubId}. Ce message est normal s'il s'agit de la première découverte du hub", __FILE__));
                        continue;
                    }
                    $hub->checkAndUpdateCmd($key, $activity);
                }
                break;
            default:
                log::add('harmonyhub', 'warning', "Unexpected message from daemon '{$key}' => " . json_encode($data));
                break;
        }
    }

    echo 'OK';
} catch (Exception $e) {
    log::add('harmonyhub', 'error', displayException($e));
}
