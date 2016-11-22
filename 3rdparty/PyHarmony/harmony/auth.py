#!/usr/bin/env python3
# -*- coding: utf-8 -*-

# Copyright (c) 2013, Jeff Terrace
# All rights reserved.

"""Authentication routines to conect to Logitech web service and Harmony devices."""

import json
import logging
import re
import requests
import sleekxmpp
from sleekxmpp.xmlstream import ET

logger = logging.getLogger(__name__)

#Logitech authentication service URL.
LOGITECH_AUTH_URL_1 = ('https://setup.myharmony.com/martiniweb/account/'
                      'ProceedWithLIPLogin?provider=hp&state=&toucheck=True')
LOGITECH_AUTH_URL_2 = ('https://svcs.myharmony.com/CompositeSecurityServices/'
                        'Security.svc/json2/signin')


def login(username, password):
    """Logs in to the Logitech Harmony web service.

    Args:
        username (str): The username (email address).
        password (str): The user's password.

    Returns:
        A base64-encoded string containing a 48-byte Login Token.
    """
    headers = {'content-type': 'application/json; charset=utf-8'}
    data = {'email': username, 'password': password}
    data = json.dumps(data)
    resp = requests.post(LOGITECH_AUTH_URL_1, headers=headers, data=data)
    if resp.status_code != 200:
        logger.critical('Received response code %d from Logitech.',resp.status_code)
        logger.critical('Data: \n%s\n', resp.text)
        raise ValueError('Logitech login failed')
        return

    resp_dict = json.loads(resp.json())
    id_token = resp_dict['id_token']
    access_token = resp_dict['access_token']
    refresh_token = resp_dict['refresh_token']
    expires_in = resp_dict['expires_in']
    email_verified = resp_dict['email_verified']
    logger.debug('First stage authentication results:')
    logger.debug('id_token:', id_token)
    logger.debug('access_token:', access_token)
    logger.debug('refresh_token:', refresh_token)
    logger.debug('expires_in:', expires_in)
    logger.debug('email_verified:', email_verified)

    data = {'id_token': id_token, 'access_token': access_token }
    data = json.dumps(data)
    resp = requests.post(LOGITECH_AUTH_URL_2, headers=headers, data=data)
    logger.debug('Second stage authentication results:')
    logger.debug('AuthToken:', resp.json().get('AuthToken', None))
    logger.debug('IsNewUser:', resp.json().get('IsNewUser', None))
    logger.debug('Email:', resp.json().get('Email', None))
    logger.debug('IsLockedOut:', resp.json().get('IsLockedOut', None))
    logger.debug('AccountId:', resp.json().get('AccountId', None))

    token = resp.json().get('AuthToken', None)
    if not token:
        logger.critical('Malformed JSON (AuthToken): %s', resp.json())
        raise ValueError('Logitech login failed')
        return
    return token


class SwapAuthToken(sleekxmpp.ClientXMPP):
    """An XMPP client for swapping a Login Token for a Session Token.

    After the client finishes processing, the uuid attribute of the class will
    contain the session token.
    """

    def __init__(self, token):
        """Initializes the client.

        Args:
          token: The base64 string containing the 48-byte Login Token.

        """
        plugin_config = {
            # Enables PLAIN authentication which is off by default.
            'feature_mechanisms': {'unencrypted_plain': True},
        }
        super(SwapAuthToken, self).__init__(
            'guest@connect.logitech.com/gatorade.', 'gatorade.', plugin_config=plugin_config)

        self.token = token
        self.uuid = None
        self.add_event_handler('session_start', self.session_start)

    def session_start(self, _):
        """Called when the XMPP session has been initialized."""
        iq_cmd = self.Iq()
        iq_cmd['type'] = 'get'
        action_cmd = ET.Element('oa')
        action_cmd.attrib['xmlns'] = 'connect.logitech.com'
        action_cmd.attrib['mime'] = 'vnd.logitech.connect/vnd.logitech.pair'
        action_cmd.text = 'token=%s:name=%s' % (self.token,
                                                'foo#iOS6.0.1#iPhone')
        iq_cmd.set_payload(action_cmd)
        result = iq_cmd.send(block=True)
        payload = result.get_payload()
        assert len(payload) == 1
        oa_resp = payload[0]
        assert oa_resp.attrib['errorcode'] == '200'
        match = re.search(r'identity=(?P<uuid>[\w-]+):status', oa_resp.text)
        assert match
        self.uuid = match.group('uuid')
        logger.info('Received UUID from device: %s', self.uuid)
        self.disconnect(send_close=False)


def swap_auth_token(ip_address, port, token):
    """Swaps the Logitech auth token for a session token.

    Args:
        ip_address (str): IP Address of the Harmony device IP address
        port (str): Harmony device port
        token (str): A base64-encoded string containing a 48-byte Login Token.

    Returns:
        A string containing the session token.
    """
    login_client = SwapAuthToken(token)
    login_client.connect(address=(ip_address, port),use_tls=False, use_ssl=False)
    login_client.process(block=True)
    return login_client.uuid
