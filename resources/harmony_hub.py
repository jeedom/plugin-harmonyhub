from __future__ import annotations

import logging
from typing import Callable
from aioharmony.harmonyapi import HarmonyAPI
from aioharmony.const import ClientCallbackType, SendCommandDevice
import aioharmony.exceptions as aioexc

from consts import CURRENT_ACTIVITY, STARTING_ACTIVITY

class HarmonyHub():

    def __init__(self, ip: str, on_activty_change: Callable[[any, str, tuple], None]):
        self.__api: HarmonyAPI
        self.__logger = logging.getLogger(__name__)

        self.__on_activty_change = on_activty_change
        self.__ip = ip
        self._connected = False

    @property
    def ip_address(self) -> str:
        return self.__api.ip_address

    @property
    def hub_id(self) -> str:
        return self.__api.hub_id

    @property
    def name(self) -> str:
        return self.__api.name

    @property
    def connected(self):
        return self._connected

    @property
    def json_config(self) -> dict:
        """Returns configuration as a dictionary (json)"""
        return self.__api.json_config

    @property
    def current_activity_name(self) -> str:
        return self.__api.current_activity[1]

    async def connect(self):
        if self._connected:
            self.__logger.warning("Already connected to %s, disconnect first", self.__ip)
            return

        self.__logger.debug("Connecting %s", self.__ip)

        callbacks = {
            "config_updated": self._on_config_updated,
            "connect": self._on_connect,
            "disconnect": self._on_disconnect,
            "new_activity_starting": self._on_new_activity_starting,
            "new_activity": self._on_new_activity,
        }

        self.__api = HarmonyAPI(ip_address=self.__ip, callbacks=ClientCallbackType(**callbacks))
        self._connected = False
        try:
            self._connected = await self.__api.connect()
        except (TimeoutError, aioexc.TimeOut) as err:
            await self.__api.close()
            raise Exception(f"Connection timed-out to {self.__ip}:8088") from err
        except (ValueError, AttributeError) as err:
            await self.__api.close()
            raise Exception(f"Error {err} while connecting HUB at:{self.__ip}:8088") from err
        if not self._connected:
            await self.__api.close()
            raise Exception(f"Unable to connect to HUB at: {self.__ip}:8088")

        self.__logger.info("Connected to %s (%s) on %s", self.name, self.hub_id, self.ip_address)

    async def disconnect(self):
        if not self._connected:
            return

        try:
            self.__logger.debug("%s (%s): Closing", self.name, self.ip_address)
            await self.__api.close()
            self._connected = False
        except aioexc.TimeOut:
            self.__logger.warning("%s (%s): Close timed-out", self.name, self.ip_address)

    def _on_config_updated(self, config: dict | None = None) -> None:
        self.__logger.debug("%s: config_updated: %s", self.name, config)

    def _on_connect(self, ip: str | None = None) -> None:
        self.__logger.info("%s: connected on %s", self.name, ip)
        self._connected = True

    def _on_disconnect(self, msg: str | None = None) -> None:
        self.__logger.info("%s: disconnected: %s", self.name, msg)
        self._connected = False

    def _on_new_activity_starting(self, activity_info: tuple) -> None:
        if activity_info[0] == -1:
            return
        self.__logger.debug("%s: activity %s starting", self.name, activity_info)
        self.__on_activty_change(self, STARTING_ACTIVITY, activity_info)

    def _on_new_activity(self, activity_info: tuple) -> None:
        self.__logger.debug("%s: activity %s started", self.name, activity_info)
        self.__on_activty_change(self, STARTING_ACTIVITY, ('',''))
        self.__on_activty_change(self, CURRENT_ACTIVITY, activity_info)

    async def start_activity(self, activity_id: str):
        current_activity_id, current_activity_name = self.__api.current_activity
        self.__logger.debug(f"current activity {current_activity_name} ({current_activity_id})")
        if str(current_activity_id) == activity_id:
            # Automations or HomeKit may turn the device on multiple times
            # when the current activity is already active which will cause
            # harmony to loose state.  This behavior is unexpected as turning
            # the device on when its already on isn't expected to reset state.
            self.__logger.info("%s: Current activity is already %s", self.name, current_activity_name)
            return
        try:
            self.__logger.info("%s: start activity %s", self.name, self.__api.get_activity_name(activity_id))
            await self.__api.start_activity(activity_id)
        except aioexc.TimeOut:
            self.__logger.error("%s: Starting activity %s timed-out", self.name, activity_id)

    async def send_command(self, device_id: str, command: str):
        self.__logger.info('%s: send command %s to device %s', self.name, command, self.__api.get_device_name(device_id))
        snd_cmmnd = SendCommandDevice(
            device=device_id,
            command=command,
            delay=0)
        await self.__api.send_commands(snd_cmmnd)