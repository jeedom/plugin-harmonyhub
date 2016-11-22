#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""Module for querying and controlling Logitech Harmony devices."""

import argparse
import json
import logging
import auth as harmony_auth
import client as harmony_client
import sys
import time

logger = logging.getLogger(__name__)
logger.setLevel(logging.ERROR)

# Trim down log file spam
logging.getLogger('sleekxmpp').setLevel(logging.CRITICAL)
logging.getLogger('requests').setLevel(logging.CRITICAL)
logging.getLogger('urllib3').setLevel(logging.CRITICAL)
logging.getLogger('pyharmony').setLevel(logging.CRITICAL)


def pprint(obj):
    """Pretty JSON dump of an object."""
    print(json.dumps(obj, sort_keys=True, indent=4, separators=(',', ': ')))


def login_to_logitech(email, password, harmony_ip, harmony_port):
    """Logs in to the Logitech service.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        Session token used to log in to the Harmony device.
    """
    token = harmony_auth.login(email, password)
    if not token:
        logger.warning('Could not get token from Logitech server.')

    session_token = harmony_auth.swap_auth_token(harmony_ip, harmony_port, token)
    if not session_token:
        logger.warning('Could not swap login token for session token.')

    return session_token


def get_client(email, password, harmony_ip, harmony_port):
    """Connect to the Harmony and return a Client instance.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        object: Authenticated client instance.
    """
    token = login_to_logitech(email, password, harmony_ip, harmony_port)
    client = harmony_client.create_and_connect_client(harmony_ip, harmony_port, token)
    return client


# Functions for use when module is imported

def ha_get_token(username, password):
    token = harmony_auth.login(username, password)
    return token


def ha_get_client(token, harmony_ip, harmony_port):
    """Connect to the Harmony and return a Client instance.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        object: Authenticated client instance.
    """
    client = harmony_client.create_and_connect_client(harmony_ip, harmony_port, token)
    return client


def ha_get_config(token, harmony_ip, harmony_port):
    """Connects to the Harmony and generates a dictionary containing all activites and commands programmed to hub.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        Dictionary containing Harmony device configuration
    """
    client = ha_get_client(token, harmony_ip, harmony_port)
    config = client.get_config()
    client.disconnect(send_close=True)
    return config


def ha_get_config_file(config, path):
    """Connects to the Harmony and generates a text file containing all activites and commands programmed to hub.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port
        path (str): Full path to file that function will write activity and command information

    Returns:
        True
    """
    with open(path, 'w+') as file_out:
        file_out.write('Activities\n')
        for activity in config['activity']:
            file_out.write('  ' + activity['id'] + ' - ' + activity['label'] + '\n')

        file_out.write('\nDevice Commands\n')
        for device in config['device']:
            file_out.write('  ' + device['id'] + ' - ' + device['label'] + '\n')
            for controlGroup in device['controlGroup']:
                for function in controlGroup['function']:
                    file_out.write('    ' + function['name'] + '\n')
    return True


def ha_get_activities(config):
    """Connects to the Harmony hub and returns configured activities.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        Dictionary containing activity label and ID number.
    """
    #config = ha_get_config(email, password, harmony_ip, harmony_port)
    activities = {}
    for activity in config['activity']:
        activities[activity['label']] = activity['id']
    if activities != {}:
        return activities
    else:
        logger.error('Unable to retrieve hub\'s activities')
        return activities


def ha_get_current_activity(token, config, harmony_ip, harmony_port):
    """Returns Harmony hub's current activity.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        String containing hub's current activity.
    """
    client = ha_get_client(token, harmony_ip, harmony_port)
    current_activity_id = client.get_current_activity()
    client.disconnect(send_close=True)
    activity = [x for x in config['activity'] if int(x['id']) == current_activity_id][0]
    if type(activity) is dict:
        return activity['label']
    else:
        logger.error('Unable to retrieve current activity')
        return 'Unknown'


def ha_start_activity(token, harmony_ip, harmony_port, config, new_activity):
    """Connects to Harmony Hub and starts an activity

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port
        new_actvivity (str): Activity ID or label to start

    Returns:
        True if activity started, otherwise False
    """
    client = ha_get_client(token, harmony_ip, harmony_port)
    status = False

    if (new_activity.isdigit()) or (new_activity == '-1'):
        status = client.start_activity(new_activity)
        client.disconnect(send_close=True)
        if status:
            return True
        else:
            logger.info('Activity start failed')
            return False

    else:
        activities = config['activity']
        labels_and_ids = dict([(a['label'], a['id']) for a in activities])
        matching = [label for label in list(labels_and_ids.keys())
                    if new_activity.lower() in label.lower()]
        if len(matching) == 1:
            activity = matching[0]
            logger.info('Found activity named %s (id %s)' % (activity, labels_and_ids[activity]))
            status = client.start_activity(labels_and_ids[activity])
    client.disconnect(send_close=True)
    if status:
        return True
    else:
        logger.error('Unable to find matching activity, start failed %s' % (' '.join(new_activity)))
        return False


def ha_power_off(token, harmony_ip, harmony_port):
    """Power off Harmony Hub.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        True if PowerOff activity started, otherwise False

    """
    client = ha_get_client(token, harmony_ip, harmony_port)
    status = client.power_off()
    client.disconnect(send_close=True)
    if status:
        return True
    else:
        logger.error('Power Off failed')
        return False


def ha_send_command(token, harmony_ip, harmony_port, device, command):
    """Connects to the Harmony and send a simple command.

    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port
        device_id (str): Device ID from Harmony Hub configuration to control
        new_command (str): Command from Harmony Hub configuration to control

    Returns:
        Completion status
    """
    client = ha_get_client(token, harmony_ip, harmony_port)
    client.send_command(device, command)
    time.sleep(1)
    client.disconnect(send_close=True)
    return 0


def ha_sync(token, harmony_ip, harmony_port):
    """Syncs Harmony hub to web service.
    Args:
        email (str):  Email address used to login to Logitech service
        password (str): Password used to login to Logitech service
        harmony_ip (str): Harmony hub IP address
        harmony_port (str): Harmony hub port

    Returns:
        Completion status
    """
    client = ha_get_client(token, harmony_ip, harmony_port)
    client.sync()
    client.disconnect(send_close=True)
    return 0


# Functions for use on command line
def show_config(args):
    """Connects to the Harmony and prints its configuration."""
    client = get_client(args.email, args.password, args.harmony_ip, args.harmony_port)
    config=client.get_config()
    retjson='{"device" : [{'
    for device in config['device']:
        listused=[]
        retjson+='"name" :"'+device['label']+'","id" :"'+device['id']+'","commands":['
        for group in device['controlGroup']:
            for command in group['function']:
                cmdname=command['label']
                if cmdname in listused:
                    for x in range (0,10):
                        if cmdname+str(x) not in listused:
                            cmdname=cmdname+str(x)
                            break
                        else:
                            continue
                retjson+='"'+cmdname+'@$@'+json.loads(command['action'])['command']+'",'
                listused.append(cmdname)
            retjson=retjson[:-1]+','
        if retjson[-1:]=='[':
            retjson=retjson+']},{'
        else:
            retjson=retjson[:-1]+']},{'
    retjson=retjson[:-2]+'],"activity":[{'
    for activity in config['activity']:
        retjson+='"name" :"'+activity['label']+'","id" :"'+activity['id']+'"},{'
    retjson=retjson[:-2]+']}'
    print retjson
    #print(config)
    client.disconnect(send_close=True)
    return 0


def show_current_activity(args):
    """Returns Harmony hub's current activity.

    Args:
        args (argparse): Argparse object containing required variables from command line

    """
    client = get_client(args.email, args.password, args.harmony_ip, args.harmony_port)
    config = client.get_config()
    current_activity_id = client.get_current_activity()
    client.disconnect(send_close=True)
    activity = [x for x in config['activity'] if int(x['id']) == current_activity_id][0]
    if type(activity) is dict:
        print(activity['label'])
    else:
        logger.error('Unable to retrieve current activity')
        print('Unknown')


def start_activity(args):
    """Connects to Harmony Hub and starts an activity

    Args:
        args (argparse): Argparse object containing required variables from command line

    """
    client = get_client(args.email, args.password, args.harmony_ip, args.harmony_port)
    status = False

    if (args.activity.isdigit()) or (args.activity == '-1'):
        status = client.start_activity(args.activity)
        client.disconnect(send_close=True)
        if status:
            print('Started Actvivity')
        else:
            logger.info('Activity start failed')
    else:
        config = client.get_config()
        activities = config['activity']
        labels_and_ids = dict([(a['label'], a['id']) for a in activities])
        matching = [label for label in list(labels_and_ids.keys())
                    if args.activity.lower() in label.lower()]
        if len(matching) == 1:
            activity = matching[0]
            logger.info('Found activity named %s (id %s)' % (activity, labels_and_ids[activity]))
            status = client.start_activity(labels_and_ids[activity])
        client.disconnect(send_close=True)
        if status:
            print('Started:', args.activity)
        else:
            logger.error('found too many activities! %s' % (' '.join(matching)))


def power_off(args):
    """Power off Harmony Hub.

    Args:
        args (argparse): Argparse object containing required variables from command line

    """
    client = get_client(args.email, args.password, args.harmony_ip, args.harmony_port)
    status = client.power_off()
    client.disconnect(send_close=True)
    if status:
        print('Powered Off')
    else:
        logger.error('Power off failed')


def send_command(args):
    """Connects to the Harmony and send a simple command.

    Args:
        args (argparse): Argparse object containing required variables from command line

    """
    client = get_client(args.email, args.password, args.harmony_ip, args.harmony_port)
    client.send_command(args.device_id, args.command)
    client.disconnect(send_close=True)
    print('Command Sent')


def sync(args):
    """Syncs Harmony hub to web service.
    Args:
        args (argparse): Argparse object containing required variables from command line

    Returns:
        Completion status
    """
    client = get_client(args.email, args.password, args.harmony_ip, args.harmony_port)
    client.sync()
    client.disconnect(send_close=True)
    print('Sync complete')


def main():
    """Main method for the script."""
    parser = argparse.ArgumentParser(description='Pyharmony - Harmony device control',
                                     formatter_class=argparse.ArgumentDefaultsHelpFormatter)

    required_flags = parser.add_argument_group('required arguments')

    # Required flags go here.
    required_flags.add_argument('--email',
                                required=True,
                                help=('Logitech username in the form of an email address.'))
    required_flags.add_argument('--password',
                                required=True,
                                help='Logitech password.')
    required_flags.add_argument('--harmony_ip',
                                required=True,
                                help='IP Address of the Harmony device.')

    # Flags with default values go here.
    loglevels = dict((logging.getLevelName(level), level) for level in [10, 20, 30, 40, 50])
    parser.add_argument('--harmony_port',
                        required=True,
                        default=5222,
                        type=int,
                        help=('Network port that the Harmony is listening on.'))
    parser.add_argument('--loglevel',
                        default='INFO',
                        choices=list(loglevels.keys()),
                        help='Logging level to print to the console.')

    subparsers = parser.add_subparsers()

    show_config_parser = subparsers.add_parser('show_config', help='Print the Harmony device configuration.')
    show_config_parser.set_defaults(func=show_config)

    show_activity_parser = subparsers.add_parser('show_current_activity', help='Print the current activity config.')
    show_activity_parser.set_defaults(func=show_current_activity)

    start_activity_parser = subparsers.add_parser('start_activity', help='Switch to a different activity.')
    start_activity_parser.add_argument('--activity', help='Activity to switch to, id or label.')
    start_activity_parser.set_defaults(func=start_activity)

    power_off_parser = subparsers.add_parser('power_off', help='Stop the activity.')
    power_off_parser.set_defaults(func=power_off)

    sync_parser = subparsers.add_parser('sync', help='Sync the harmony.')
    sync_parser.set_defaults(func=sync)

    command_parser = subparsers.add_parser('send_command', help='Send a simple command.')
    command_parser.add_argument('--device_id', help='Specify the device id to which we will send the command.')
    command_parser.add_argument('--command', help='IR Command to send to the device.')
    command_parser.set_defaults(func=send_command)

    args = parser.parse_args()

    logging.basicConfig(
        level=loglevels[args.loglevel],
        format='%(levelname)s:\t%(name)s\t%(message)s')

    sys.exit(args.func(args))


if __name__ == '__main__':
    main()
