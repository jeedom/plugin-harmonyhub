import asyncio
import logging
import ipaddress
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
        ip_list = ips.split('|') if ips else []
        ip_list = list(set(ip_list))  # Remove duplicates
        return [ip for ip in ip_list if self.__is_valid_ip_address(ip)]

    def __is_valid_ip_address(self, ip: str):
        """Validate the IP address format."""
        try:
            ipaddress.ip_address(ip)
            return True
        except ValueError:
            return False


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
            if ip in self._hubs or ip == '':
                continue
            new_hub = HarmonyHub(ip, self.on_activity_change)
            try:
                await new_hub.connect()
                self._hubs[new_hub.hub_id] = new_hub

            except Exception as e:
                self._logger.error("Exception during connect on start: %s", e)
            else:
                payload['hubs'][new_hub.hub_id] = new_hub.json_config
                payload['hubs'][new_hub.hub_id]['name'] = new_hub.name
                payload['hubs'][new_hub.hub_id]['ip_address'] = new_hub.ip_address

        if len(self._hubs) == 0:
            self._logger.error("No Harmony hubs connected, please check your configuration")
            asyncio.create_task(self.stop())
            return
        await self.send_to_jeedom(payload)

        asyncio.create_task(self.__health_check_task())

    async def __health_check_task(self):
        try:
            delay = 300
            self._logger.info("[Health check]: Starting with a delay of %d seconds", delay)
            while True:
                await asyncio.sleep(delay)
                for hub in self._hubs.values():
                    if not hub.connected:
                        self._logger.info("[Health check]: Hub with ip %s is not connected, trying to reconnect", hub.ip_address)
                        try:
                            await hub.connect()
                            self._logger.info("[Health check]: Reconnected to hub with ip %s", hub.ip_address)
                        except Exception as e:
                            self._logger.warning("[Health check]: %s", e)
        except asyncio.CancelledError:
            self._logger.info("[Health check]: task cancelled")

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

        if not hub.connected:
            self._logger.error("Hub %s is not connected", hub_id)
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
