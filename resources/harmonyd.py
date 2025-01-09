import logging
from jeedomdaemon.base_daemon import BaseDaemon
from jeedomdaemon.base_config import BaseConfig

from harmony_hub import HarmonyHub


class HarmonyConfig(BaseConfig):
    """This is where you declare your custom argument/configuration

    Remember that all usual arguments are managed by the BaseConfig class already so you only have to take care of yours; e.g. user & password in this case
    """
    def __init__(self):
        super().__init__()

        self.add_argument("--harmony_ip", type=str)

    @property
    def harmony_ip(self) -> list[str]:
        ips: str = self._args.harmony_ip
        return ips.split('|')


class HarmonyDaemon(BaseDaemon):
    """This is the main class of you daemon"""

    def __init__(self) -> None:
        # Standard initialisation
        self._config = HarmonyConfig()
        super().__init__(self._config, on_start_cb=self.on_start, on_message_cb=self.on_message, on_stop_cb=self.on_stop)

        logging.getLogger('slixmpp').setLevel(logging.ERROR)

        self._hubs: dict[str, HarmonyHub] = {}

    async def on_start(self):
        payload = {'hubs': {}}
        for ip in self._config.harmony_ip:
            new_hub = HarmonyHub(ip, self.on_activity_change)
            await new_hub.connect()
            self._hubs[new_hub.hub_id] = new_hub

            payload['hubs'][new_hub.hub_id] = new_hub.json_config
            payload['hubs'][new_hub.hub_id]['name'] = new_hub.name
            payload['hubs'][new_hub.hub_id]['ip_address'] = new_hub.ip_address

        await self.send_to_jeedom(payload)

    async def on_message(self, message: list):
        """
        This function will be called once a message is received from Jeedom; check on api key is done already, just care about your logic
        You must implement the different actions that your daemon can handle.
        """
        try:
            hub_id = str(message['hub_id'])
        except KeyError:
            self._logger.error("Missing hub id in message")
            return

        try:
            hub = self._hubs[hub_id]
        except KeyError:
            self._logger.error("Unknown hub ID: %s", hub_id)
            return

        if message['action'] == 'start_activity':
            await hub.start_activity(str(message['activity_id']))
        elif message['action'] == 'send_command':
            await hub.send_command(str(message['device_id']), message['command'])
        else:
            self._logger.warning('Unknown action: %s', message['action'])

    async def on_stop(self):
        for hub in self._hubs.values():
            await hub.disconnect()

    def on_activity_change(self, hub: HarmonyHub, type: str, activity_info: tuple):
        self._logger.info("%s: %s %s", hub.name, type, activity_info)
        self.create_task_add_change(f'{type}::{hub.hub_id}', activity_info[1])


HarmonyDaemon().run()
