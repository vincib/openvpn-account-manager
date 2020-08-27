#!/bin/sh

ip link set up dev tun0
ip addr add 10.5.0.1/24 dev tun0

