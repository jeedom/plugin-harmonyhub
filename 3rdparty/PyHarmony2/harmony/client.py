#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""Client class for connecting to Logitech Harmony devices."""

import json
import re
import asyncio
import websockets
from aiohttp import ClientSession
from urllib.parse import urlparse

import logging

DEFAULT_CMD = 'vnd.logitech.connect'
DEFAULT_DISCOVER_STRING = '_logitech-reverse-bonjour._tcp.local.'
DEFAULT_DOMAIN = 'svcs.myharmony.com'
DEFAULT_HUB_PORT = '8088'

logger = logging.getLogger(__name__)

class HarmonyClient():
    """An websocket client for connecting to the Logitech Harmony devices."""

    def __init__(self, ip_address):
        self._ip_address = ip_address
        self._friendly_name = None
        self._remote_id = None
        self._domain = DEFAULT_DOMAIN
        self._email = None
        self._account_id = None
        self._websocket = None
        self._msgid = 0
        self._config = None
        self._activities = None
        self._devices = None

    @property
    def config(self):
        return self._config

    @property
    def json_config(self):
        """Returns configuration as a dictionary (json)"""

        result = {}

        activity_dict = {}
        for activity in self._config.get('activity'):
            activity_dict.update({activity['id']: activity['label']})

        result.update(Activities=activity_dict)

        devices_dict = {}
        for device in self._config.get('device'):
            command_list = []
            for controlGroup in device['controlGroup']:
                for function in controlGroup['function']:
                    action = json.loads(function['action'])
                    command_list.append(action.get('command'))

            device_dict = {
                'id'      : device.get('id'),
                'commands': command_list
            }

            devices_dict.update({device.get('label'): device_dict})

        result.update(Devices=devices_dict)

        return result

    @property
    def name(self):
        return self._friendly_name

    @property
    def email(self):
        return self._email

    @property
    def account_id(self):
        return self._account_id

    async def retrieve_hub_info(self):
        """Retrieve the harmony Hub information."""
        logger.debug("Retrieving Harmony Hub information.")
        url = 'http://{}:{}/'.format(self._ip_address, DEFAULT_HUB_PORT)
        headers = {
            'Origin': 'http://localhost.nebula.myharmony.com',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Accept-Charset': 'utf-8',
        }
        json_request = {
            "id ": 1,
            "cmd": "connect.discoveryinfo?get",
            "params": {}
        }
        async with ClientSession() as session:
            async with session.post(
                url, json=json_request, headers=headers) as response:
                json_response = await response.json()
                self._friendly_name = json_response['data']['friendlyName']
                self._remote_id = str(json_response['data']['remoteId'])
                domain = urlparse(json_response['data']['discoveryServerUri'])
                self._domain = domain.netloc if domain.netloc else \
                    DEFAULT_DOMAIN
                self._email = json_response['data']['email']
                self._account_id = str(json_response['data']['accountId'])

    async def _perform_connect(self):
        """Connect to Hub Web Socket"""
        # Return connected if we are already connected.
        if self._websocket:
            if self._websocket.open:
                return True

            if not self._websocket.closed:
                await self.disconnect()

        logger.debug("Starting connect.")
        if self._remote_id is None:
            # We do not have the remoteId yet, get it first.
            await self.retrieve_hub_info()

        if self._remote_id is None:
            #No remote ID means no connect.
            return False

        logger.debug("Connecting to %s for hub %s",
                     self._ip_address, self._remote_id)
        self._websocket = await websockets.connect(
            'ws://{}:{}/?domain={}&hubId={}'.format(
            self._ip_address, DEFAULT_HUB_PORT, self._domain, self._remote_id
            )
        )

    async def connect(self):
            """Connect to Hub Web Socket"""
            await self._perform_connect()

            response = await self._send_request(
                '{}/vnd.logitech.statedigest?get'.format(DEFAULT_CMD)
            )

            if response.get('code') != 200:
                await self.disconnect()
                return False

            self._current_activity = response['data']['activityId']
            return True

    async def disconnect(self, send_close=None):
        """Disconnect from Hub"""
        logger.debug("Disconnecting from %s", self._ip_address)
        await self._websocket.close()
        self._websocket = None

    async def _send_request(self, command, params=None,
                            wait_for_response=True, msgid=None):
        """Send a payload request to Harmony Hub and return json response."""
        # Make sure we're connected.
        await self._perform_connect()

        if params is None:
            params = {
                "verb"  : "get",
                "format": "json"
            }

        if not msgid:
            msgid = self._msgid = self._msgid + 1

        payload = {
            "hubId"  : self._remote_id,
            "timeout": 30,
            "hbus"   : {
                "cmd": command,
                "id" : msgid,
                "params": params
            }
        }

        logger.debug("Sending payload: %s", payload)
        await self._websocket.send(json.dumps(payload))

        if not wait_for_response:
            return
        return await self._wait_response(msgid)

    async def _wait_response(self, msgid):
        """Await message on web socket"""
        logger.debug("Waiting for response")
        while True:
            response_json = await self._websocket.recv()
            response = json.loads(response_json)
            logger.debug("Received response: %s", response)
            if response.get('id') == msgid:
                return response


    async def get_config(self):
        """Retrieves the Harmony device configuration.

        Returns:
            A nested dictionary containing activities, devices, etc.
        """
        logger.debug("Getting configuration")
        response = await self._send_request(
									 
														   
									 
            'vnd.logitech.harmony/vnd.logitech.harmony.engine?config'
        )
				   
				   

        assert response.get('code') == 200
				
												
					 
							 
															 
							
					
						
													 

        self._config = response.get('data')
								
							   
													  
									 
									  

        self._activities = list(
            {'name': a['label'],
             'name_match': a['label'].lower(),
             'id': int(a['id'])
             } for a in self._config.get('activity'))

        self._devices = list(
            {'name': a['label'],
             'name_match': a['label'].lower(),
             'id': int(a['id'])
             } for a in self._config.get('device'))

        return self._config

    async def get_current_activity(self):
        """Retrieves the current activity ID.

        Returns:
            A int with the current activity ID.
        """
        logger.debug("Retrieving current activity")
        response = await self._send_request(
									 
														   
									 
            'vnd.logitech.harmony/vnd.logitech.harmony.engine'
            '?getCurrentActivity'
			
											
						 
													 
											
									  
        )
							   
													  
											 
							   

        assert response.get('code') == 200
        activity = response['data']['result']
        return int(activity)

    async def start_activity(self, activity_id):
        """Starts an activity.

        Args:
            activity_id: An int or string identifying the activity to start

        Returns:
            True if activity started, otherwise False
        """
        logger.debug("Starting activity %s", activity_id)
        params = {
            "async": "true",
            "timestamp": 0,
            "args": {
                "rule": "start"
            },
            "activityId": str(activity_id)
        }
        msgid = self._msgid = self._msgid + 1
        response = await self._send_request(
            'harmony.activityengine?runactivity', params, True, msgid
        )
													 
											
									  
								
							   
								   
					   
			 
						

        # Wait for the activity to complete.
        while True:
            # Make sure response is related to start activity.
            if not response.get('cmd'):
                response = await self._wait_response(msgid)
                continue

            if response.get('cmd') == 'harmony.engine?startActivityFinished':
                return True

            if response.get('cmd') != 'harmony.engine?startActivity' and \
                    response.get('cmd') != 'harmony.engine?helpdiscretes':
                response = await self._wait_response(msgid)
                continue

            # Response of 200 means success.
            if response.get('code') == 200:
                return True

            # Response of 100 means in progress
            # Any other response considered a failure.
            if response.get('code') != 100:
                return False

            response = await self._wait_response(msgid)

    async def sync(self):
        """Syncs the harmony hub with the web service.
        """
						  
							  
									 
														   
												  
									  
			
											
						 
													 
											
									  
								

        logger.debug("Performing sync")
        response = await self._send_request(
            'setup.sync'
        )

        return True

    async def send_command(self, device, command, command_delay=0):
        """Send a simple command to the Harmony Hub.

        Args:
            device_id (str): Device ID from Harmony Hub configuration to control
            command (str): Command from Harmony Hub configuration to control
            command_delay (int): Delay in seconds between sending the press command and the release command.

        Returns:
            None if successful
        """
        logger.debug("Sending command %s to device %s with delay %ss",
                     command, device, command_delay)
        params = {
            "status": "press",
            "timestamp": '0',
            "verb": "render",
            "action": '{{"command": "{}",'
                      '"type": "IRCommand",'
                      '"deviceId": "{}"}}'.format(command, device)
        }

        await self._send_request(
            'vnd.logitech.harmony/vnd.logitech.harmony.engine?holdAction',
            params, False
									  
        )
					 

        if command_delay > 0:
            await asyncio.sleep(command_delay)

        params['status'] = 'release'
        await self._send_request(
            'vnd.logitech.harmony/vnd.logitech.harmony.engine?holdAction',
            params, False
        )

    async def change_channel(self, channel):
        """Changes a channel.
        Args:
            channel: Channel number
        Returns:
          An HTTP 200 response (hopefully)
        """
        logger.debug("Changing channel to %s", channel)
        params = {
            "timestamp": 0,
														   
																	
            'channel': str(channel)
        }
        response = await self._send_request(
            'harmony.engine?changeChannel', params
											
						 
													 
											
									  
        )
							   
								   
					   
			 
						

        return response.get('code') == 200

    async def power_off(self):
        """Turns the system off if it's on, otherwise it does nothing.

        Returns:
            True if the system becomes or is off
        """
        activity = await self.get_current_activity()
        if activity != -1:
            return await self.start_activity(-1)
        else:
            return True

    def register_activity_callback(self, activity_callback):
        """Register a callback that is executed on activity changes."""
        def hub_event(xml):
            match = re.search('activityId=(-?\d+)', xml.get_payload()[0].text)
            activity_id = match.group(1)
            if activity_id is not None:
                activity_callback(int(activity_id))

        self.registerHandler(Callback('Activity Finished', MatchHarmonyEvent('startActivityFinished'), hub_event))

    @staticmethod
    def search(item_name, item, list):
        """Search for the item in the list."""
        return next(
            (element for element in list
             if element[item_name] == item), None)

    def get_activity_id(self, activity_name):
        """Find the activity ID for the provided activity name."""
        item = self.search('name_match', activity_name.lower(),
                           self._activities)
        return item.get('id') if item else None

    def get_activity_name(self, activity_id):
        """Find the activity name for the provided ID."""
        item = self.search('id', activity_id, self._activities)
        return item.get('name') if item else None

    def get_device_id(self, device_name):
        """Find the device ID for the provided device name."""
        item = self.search('name_match', device_name.lower(), self._devices)
        return item.get('id') if item else None

    def get_device_name(self, device_id):
        """Find the device name for the provided ID."""
        item = self.search('id', device_id, self._devices)
        return item.get('name') if item else None



# class MatchHarmonyEvent(MatcherBase):
#     def match(self, xml):
#         """Check if a stanza matches the Harmony event criteria."""
#         payload = xml.get_payload()
#         if len(payload) == 1:
#             msg = payload[0]
#             if msg.tag == '{connect.logitech.com}event' and msg.attrib['type'] == 'harmony.engine?' + self._criteria:
#                 return True
#         return False


async def create_and_connect_client(ip_address, port=None,
                                    activity_callback=None,
                                    connect_attempts=5):

    """Creates a Harmony client and initializes session.

    Args:
        ip_address (str): Harmony device IP address
        port (str): Harmony device port
        activity_callback (function): Function to call when the current activity has changed.


    Returns:
        A connected HarmonyClient instance
    """
    client = HarmonyClient(ip_address)
    i = 0
    connected = False
    while (i < connect_attempts and not connected):
	   
									
					   
        i = i + 1
        connected = await client.connect()
    if i == connect_attempts:
        logger.error("Failed to connect to %s after %d tries" % (ip_address,i))
        await client.disconnect()
        return False

    if activity_callback:
        client.register_activity_callback(activity_callback)
    return client
