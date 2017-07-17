#!/bin/bash
echo "########### Compilation en cours ##########"
sudo apt-get update  -y -q
sudo apt-get install -y python-pip python-dev
sudo pip install requests
echo "########### Fin ##########"
