#!/bin/bash

start() {
        echo -e '\n    --> Starting Firewall ...\n'

        # echo 1 > /proc/sys/net/ipv4/ip_forward
        iptables -P INPUT DROP
        iptables -P OUTPUT ACCEPT
        iptables -P FORWARD DROP

        iptables -A INPUT -p tcp -i lo -j ACCEPT

        iptables -A INPUT -p tcp --dport 80 -j ACCEPT
        iptables -A INPUT -p tcp --dport 443 -j ACCEPT

        iptables -A INPUT -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
        iptables -A INPUT -p icmp --icmp-type echo-request -m limit --limit 1/s -j ACCEPT

        iptables -A INPUT -m state --state INVALID -j DROP

        iptables -A INPUT -p udp --dport 53 -j ACCEPT
        iptables -A INPUT -p udp --sport 53 -j ACCEPT

	echo -e '    --> Firewall Status: ON \n'
}

stop() {
	echo -e '\n    --> Stopping Firewall ...\n'

	# echo 0 > /proc/sys/net/ipv4/ip_forward
    iptables -P INPUT ACCEPT
    iptables -P OUTPUT ACCEPT
    iptables -P FORWARD ACCEPT
	
	iptables -F
	iptables -t nat -F

	echo -e '    --> Firewall Status: OFF \n'
}

status() {
	echo -e '\n    --> Current Firewall Rules ...\n'

        iptables -L

	echo -e '\n'
}

restart() {
	pkill -f "sleep 58s" > /dev/null 2>/dev/null
	sleep 2s
	stop
	start
}

test() {
	sleep 58s > /dev/null 2>/dev/null && wall "Firewall Testing stopped." && ./firewall.sh stop &>/dev/null & disown;
	start
	echo -e '\n     --> Stopping Firewall in a minute (use restart() to cancel testing)...\n'
}

case "$1" in
	start)
	start
	;;

	stop)
	stop
	;;

	test)
        test
        ;;

	status)
        status
        ;;

	restart)
	restart
	;;
	*)
	echo -e '\n    --> Usage:'
	echo -e '    /./firewall.sh start|stop|test|restart\n'
	;;
esac